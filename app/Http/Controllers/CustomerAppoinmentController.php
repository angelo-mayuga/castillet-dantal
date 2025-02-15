<?php

namespace App\Http\Controllers;

use App\Http\Requests\Apointment\StoreApointmentRequest;
use App\Http\Resources\Apointment\ApointmentListResource;
use App\Models\Apointment\Apointment;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use App\Http\Requests\Apointment\UpdateApointmentRequest;
use Carbon\Carbon;

class CustomerAppoinmentController extends Controller
{
    public function index(Request $request)
    {

        $page = $request->input('page', 1); // default 1
        $perPage = $request->input('perPage', 50); // default 50
        $queryString = $request->input('query', null);
        $sort = explode('.', $request->input('sort', 'id'));
        $order = $request->input('order', 'asc');

        $data = Apointment::query()
            ->with([])
            ->where(function ($query) use ($queryString) {
                if ($queryString && $queryString != '') {
                    // filter result
                    // $query->where('column', 'like', '%' . $queryString . '%')
                    //     ->orWhere('column', 'like', '%' . $queryString . '%');
                }
            })
            ->when(count($sort) == 1, function ($query) use ($sort, $order) {
                $query->orderBy($sort[0], $order);
            })
            ->paginate($perPage)
            ->withQueryString();

        $props = [
            'data' => ApointmentListResource::collection($data),
            'params' => $request->all(),
        ];

        if ($request->wantsJson()) {
            return json_encode($props);
        }

        if (count($data) <= 0 && $page > 1) {
            return redirect()->route('apointments.index', ['page' => 1]);
        }

        return Inertia::render('Admin/Apointment/Index', $props);
    }
    public function store(StoreApointmentRequest $request)
    {
        $hasAppointment = Apointment::where('user_id', auth()->user()->id)->first();


        $validatedData = $request->validated();

        // Calculate the end time of the new appointment
        $startTime = $validatedData['time_start'];
        $endTime = date('H:i', strtotime('+1 hour', strtotime($startTime)));

        $overlappingAppointment = Apointment::where('date', $validatedData['date'])->whereTime('time_start', $startTime)

            ->first();

        if ($overlappingAppointment) {
            throw ValidationException::withMessages([
                'date' => 'An appointment already exists ',
            ]);
        }
        if ($hasAppointment) {
            throw ValidationException::withMessages([
                'date' => 'You already set an appointment',
            ]);
        }


        $validatedData['time_end'] = $endTime;
        $validatedData['status'] = 'Pending';

        //    dd($validatedData);
        $newAppointment = Apointment::create($validatedData);
        sleep(1);

        if ($request->wantsJson()) {
            return new ApointmentListResource($newAppointment);
        }

        return redirect()->back();
    }
    public function update(UpdateApointmentRequest $request, string $id)
    {


        $data = Apointment::findOrFail($id);
        $validatedData = $request->validated();

        // Calculate the end time of the new appointment
        $startTime = $validatedData['time_start'];
        $endTime = date('H:i', strtotime('+1 hour', strtotime($startTime)));

        $overlappingAppointment = Apointment::where('date', $validatedData['date'])->whereTime('time_start', $startTime)

            ->where('id', '!=', $id)
            ->first();

        if ($overlappingAppointment) {
            throw ValidationException::withMessages([
                'date' => 'An appointment already exists ',
            ]);
        }

        $validatedData['time_end'] = $endTime;
        $validatedData['status'] = 'Pending';

        // dd($data);
        $data->update($validatedData);
        sleep(1);

        if ($request->wantsJson()) {
            return (new ApointmentListResource($data))
                ->response()
                ->setStatusCode(201);
        }

        return redirect()->back();
    }
    public function destroy(Request $request, string $id)
    {
        $data = Apointment::findOrFail($id);
        $data->delete();
        sleep(1);

        if ($request->wantsJson()) {
            return response(null, 204);
        }
        return redirect()->back();
    }
}
