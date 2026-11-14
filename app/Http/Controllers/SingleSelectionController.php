<?php
namespace App\Http\Controllers;
use App\Models\Selection;
use App\Services\CastingCalendar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
class SingleSelectionController extends Controller {
    public function index(CastingCalendar $calendar)
    {
        return view('casting.settings', [
            'selection'=>Selection::with('phases')->latest('id')->firstOrFail(),
            'openingDate'=>$calendar->openingDate(),
        ]);
    }
    public function update(Request $request, CastingCalendar $calendar)
    {
        $data = $request->validate([
            'title'=>['required','string','max:160'],
            'is_open'=>['required','boolean'],
            'starts_at'=>['nullable','date_format:Y-m-d\TH:i'],
            'ends_at'=>['nullable','date_format:Y-m-d\TH:i'],
        ]);
        $startsAt = isset($data['starts_at'])
            ? CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['starts_at'], CastingCalendar::TIMEZONE)
            : null;
        $endsAt = isset($data['ends_at'])
            ? $calendar->closingDate(CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['ends_at'], CastingCalendar::TIMEZONE))
            : null;

        if ($startsAt && $endsAt && $endsAt->lt($startsAt)) {
            throw ValidationException::withMessages(['ends_at'=>'La data finale deve seguire quella iniziale.']);
        }

        DB::transaction(function () use ($data, $startsAt, $endsAt) {
            $selection = Selection::latest('id')->lockForUpdate()->firstOrFail();
            $selection->update(collect($data)->only(['title','is_open'])->all());
            $selection->phases()->firstOrFail()->update([
                'starts_at'=>$startsAt?->utc(),
                'ends_at'=>$endsAt?->utc(),
            ]);
        });

        return back()->with('saved','Apertura delle audizioni aggiornata.');
    }
}
