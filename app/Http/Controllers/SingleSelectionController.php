<?php
namespace App\Http\Controllers;
use App\Models\Selection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
class SingleSelectionController extends Controller {
    public function index(){return view('casting.settings',['selection'=>Selection::with('phases')->latest('id')->firstOrFail()]);}
    public function update(Request $request){
        $data=$request->validate(['title'=>['required','string','max:160'],'is_open'=>['required','boolean'],'starts_at'=>['nullable','date_format:Y-m-d\TH:i'],'ends_at'=>['nullable','date_format:Y-m-d\TH:i']]);
        if(!empty($data['starts_at']) && !empty($data['ends_at']) && $data['ends_at']<$data['starts_at'])throw \Illuminate\Validation\ValidationException::withMessages(['ends_at'=>'La data finale deve seguire quella iniziale.']);
        DB::transaction(function()use($data){$selection=Selection::latest('id')->lockForUpdate()->firstOrFail();$selection->update(collect($data)->only(['title','is_open'])->all());$phase=$selection->phases()->firstOrFail();$phase->update(['starts_at'=>!empty($data['starts_at'])?Carbon::createFromFormat('!Y-m-d\TH:i',$data['starts_at'],'Europe/Rome')->utc():null,'ends_at'=>!empty($data['ends_at'])?Carbon::createFromFormat('!Y-m-d\TH:i',$data['ends_at'],'Europe/Rome')->utc():null]);});
        return back()->with('saved','Apertura delle audizioni aggiornata.');
    }
}
