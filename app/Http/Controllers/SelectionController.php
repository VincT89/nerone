<?php
namespace App\Http\Controllers;
use App\Models\Selection;
use Illuminate\Http\Request;
class SelectionController extends Controller {
    public function index(){return view('casting.selections',['selections'=>Selection::with('phases')->withCount('auditions')->latest('id')->get()]);}
    private function data(Request $request): array{return $request->validate(['title'=>['required','string','max:160'],'description'=>['nullable','string','max:4000'],'is_open'=>['required','boolean']]);}
    public function store(Request $request){$selection=Selection::create($this->data($request));$selection->phases()->create(['title'=>'Prima selezione','position'=>1]);return back()->with('saved','Selezione creata.');}
    public function update(Request $request,Selection $selection){$selection->update($this->data($request));return back()->with('saved','Selezione aggiornata.');}
}
