<?php

namespace App\Http\Controllers;

use App\Models\Nzb;
use App\Models\Category;
use App\Models\Type;
use App\Helpers\NzbParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;

class NzbController extends Controller
{
    public function __construct(private NzbParser $nzbParser)
    {
    }

    public function index()
    {
        $nzbs = Nzb::select('id', 'title', 'type_id', 'category_id', 'password', 'active', 'created_at', 'updated_at')->with(['category', 'type'])->latest()->paginate(25);

        return view('nzbs.index', ['nzbs' => $nzbs]);
    }

    public function show(Nzb $nzb)
    {
        return view('nzbs.show', ['nzb' => $nzb]);
    }

    public function create(Request $request): \Illuminate\Contracts\View\Factory|\Illuminate\View\View
    {
        abort_unless(auth()->user()->group->is_owner, 403);

        return view('nzbs.create', [
            'categories'  => Category::orderBy('position')->get(),
            'types'       => Type::orderBy('position')->get(),
            'user'        => $request->user(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->group->is_owner, 403);

$request->validate([
    'nzb_file' => 'required|file',
    'password' => 'nullable|string|max:255',
    'title' => 'required|string|max:255',
    'category_id' => 'required|integer|exists:categories,id',
    'type_id' => 'required|integer|exists:types,id',
    'description' => 'nullable|string',
    'sku' => 'nullable|string|max:255',
]);

        $file = $request->file('nzb_file');
        $filename = $file->getClientOriginalName();
        $path = $file->store('nzbs');

        // Parse the NZB file
        $metadata = $this->nzbParser->parse(Storage::path($path));

        // Create the NZB record
        $nzb = new Nzb([
            'filename' => $filename,
            'path' => $path,
            'password' => $request->password,
            'metadata' => $metadata,
            'description' => $request->description,
            'sku' => $request->sku,
	    'title' => $request->title,
            'category_id' =>  $request->category_id,
            'type_id' => $request->type_id,
            'active' => true,
        ]);
        $nzb->save();

        return redirect()->back()->with('success', 'NZB file has been uploaded.');
    }

    public function destroy(Nzb $nzb)
    {
        abort_unless(auth()->user()->group->is_owner, 403);

        $nzb->delete();

        return redirect()->back()->with('success', 'NZB file has been deleted.');
    }

    public function download(Nzb $nzb)
    {
       /*if (!$nzb->attachedUsers->contains(auth()->user()) && !($nzb->user_id === auth()->user()->id) && !(auth()->user()->group->is_owner || auth()->user()->group->is_internal)) {
            return redirect()->back()->withErrors("You do NOT currently have access to this nzb.

To purchase access please visit ArcadeSystems.co.uk

If you have recently registered please make sure to provide us with your username so we can update your permissions on your account.");
        }*/

        $path = Storage::path($nzb->path);

        if (!Storage::exists($nzb->path)) {
            return redirect()->back()->withErrors('NZB file does not exist.');
        }

        return Response::download($path, $nzb->filename);
    }
}
