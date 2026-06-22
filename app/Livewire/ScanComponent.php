<?php

namespace App\Livewire;

use App\ExtendedCarbon;
use App\Models\Attendance;
use App\Models\Barcode;
use App\Models\Shift;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Ballen\Distical\Calculator as DistanceCalculator;
use Ballen\Distical\Entities\LatLong;
use Illuminate\Support\Carbon;

class ScanComponent extends Component
{
    public ?Attendance $attendance = null;
    public $shift_id = null;
    public $shifts = null;
    public ?array $currentLiveCoords = null;
    public string $successMsg = '';
    public bool $isAbsence = false;

    public function scan(string $barcode)
    {
        if (is_null($this->currentLiveCoords)) {
            return __('Invalid location');
        } else if (is_null($this->shift_id)) {
            return __('Invalid shift');
        }

        /** @var Barcode */
        $barcode = Barcode::firstWhere('value', $barcode);
        if (!Auth::check() || !$barcode) {
            return 'Invalid barcode';
        }

        $barcodeLocation = new LatLong($barcode->latLng['lat'], $barcode->latLng['lng']);
        $userLocation = new LatLong($this->currentLiveCoords[0], $this->currentLiveCoords[1]);

        if (($distance = $this->calculateDistance($userLocation, $barcodeLocation)) > $barcode->radius) {
            return __('Location out of range') . ": $distance" . "m. Max: $barcode->radius" . "m";
        }

        /** @var Attendance */
        $existingAttendance = Attendance::where('user_id', Auth::user()->id)
            ->where('date', date('Y-m-d'))
            ->where('barcode_id', $barcode->id)
            ->first();

        if (!$existingAttendance) {
            $attendance = $this->createAttendance($barcode);
            $this->successMsg = __('Attendance In Successful');
        } else {
            $attendance = $existingAttendance;
            $shift = $attendance->shift;
            $now = Carbon::now();
            $status = $attendance->status;

            if ($shift && $shift->end_time && $now->lt($now->copy()->setTimeFromTimeString($shift->end_time))) {
                $status = 'incomplete';
            }

            $attendance->update([
                'time_out' => $now->format('H:i:s'),
                'status' => $status,
            ]);
            $this->successMsg = __('Attendance Out Successful');
        }

        if ($attendance) {
            $this->setAttendance($attendance->fresh());
            Attendance::clearUserAttendanceCache(Auth::user(), Carbon::parse($attendance->date));
            return true;
        }
    }

    public function calculateDistance(LatLong $a, LatLong $b)
    {
        $distanceCalculator = new DistanceCalculator($a, $b);
        $distanceInMeter = floor($distanceCalculator->get()->asKilometres() * 1000); // convert to meters
        return $distanceInMeter;
    }

    /** @return Attendance */
    public function createAttendance(Barcode $barcode)
    {
        $now = Carbon::now();
        $date = $now->format('Y-m-d');
        $timeIn = $now->format('H:i:s');
        /** @var Shift */
        $shift = Shift::find($this->shift_id);
        $status = Carbon::now()->setTimeFromTimeString($shift->start_time)->lt($now) ? 'late' : 'present';
        return Attendance::create([
            'user_id' => Auth::user()->id,
            'barcode_id' => $barcode->id,
            'date' => $date,
            'time_in' => $timeIn,
            'time_out' => null,
            'shift_id' => $shift->id,
            'latitude' => doubleval($this->currentLiveCoords[0]),
            'longitude' => doubleval($this->currentLiveCoords[1]),
            'status' => $status,
            'note' => null,
            'attachment' => null,
        ]);
    }

    protected function setAttendance(Attendance $attendance)
    {
        $this->attendance = $attendance;
        $this->shift_id = $attendance->shift_id;
        $this->isAbsence = !in_array($attendance->status, ['present', 'late', 'incomplete']);
    }

    public function getAttendance()
    {
        if (is_null($this->attendance)) {
            return null;
        }
        return [
            'time_in' => $this->attendance?->time_in,
            'time_out' => $this->attendance?->time_out,
        ];
    }

    public function manualClockIn()
    {
        if (is_null($this->shift_id)) {
            session()->flash('error', 'Pilih shift terlebih dahulu');
            return;
        }

        $existingAttendance = Attendance::where('user_id', Auth::user()->id)
            ->where('date', date('Y-m-d'))
            ->first();

        if ($existingAttendance && $existingAttendance->time_out) {
            session()->flash('error', 'Anda sudah absen masuk dan keluar hari ini');
            return;
        }

        if (!$existingAttendance) {
            $now = Carbon::now();
            $shift = Shift::find($this->shift_id);
            $status = Carbon::now()->setTimeFromTimeString($shift->start_time)->lt($now) ? 'late' : 'present';

            $attendance = Attendance::create([
                'user_id' => Auth::user()->id,
                'date' => $now->format('Y-m-d'),
                'time_in' => $now->format('H:i:s'),
                'shift_id' => $shift->id,
                'latitude' => $this->currentLiveCoords[0] ?? null,
                'longitude' => $this->currentLiveCoords[1] ?? null,
                'status' => $status,
            ]);
            $this->setAttendance($attendance);
            $this->successMsg = __('Attendance In Successful');
        } else {
            $now = Carbon::now();
            $existingAttendance->update([
                'time_out' => $now->format('H:i:s'),
                'status' => $existingAttendance->status === 'incomplete' ? 'present' : $existingAttendance->status,
            ]);
            $this->setAttendance($existingAttendance->fresh());
            $this->successMsg = __('Attendance Out Successful');
        }

        Attendance::clearUserAttendanceCache(Auth::user(), Carbon::now());
    }

    public function mount()
    {
        $this->shifts = Shift::all();

        /** @var Attendance */
        $attendance = Attendance::where('user_id', Auth::user()->id)
            ->where('date', date('Y-m-d'))->first();
        if ($attendance) {
            $this->setAttendance($attendance);
        } else {
            // get closest shift from current time
            $closest = ExtendedCarbon::now()
                ->closestFromDateArray($this->shifts->pluck('start_time')->toArray());

            $this->shift_id = $this->shifts
                ->where(fn (Shift $shift) => $shift->start_time == $closest->format('H:i:s'))
                ->first()->id;
        }
    }

    public function render()
    {
        return view('livewire.scan');
    }
}
