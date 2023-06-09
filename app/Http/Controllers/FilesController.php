<?php

namespace App\Http\Controllers;

use App\Models\CDN\Files;
use App\Models\CDN\Folder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class FilesController extends Controller
{
    public function list(Request $request)
    {
        if (!$this->protect()) {
            return redirect()->route('dashboard');
        }

        $files = Files::orderBy('id', 'desc')
            ->with('folders', 'user');

        if ($request->folder) {
            $files = $files->whereHas('folders', function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->folder . '%');
            });
        }

        if ($request->search) {
            $files = $files->where(function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->user) {
            $files = $files->whereHas('user', function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->user . '%');
            });
        }

        $files->where('status', true);

        $files = $files->paginate((int) $request->limit ?? 20);

        $files->appends($request->all());

        return view('files.list', [
            'files' => $files
        ]);
    }

    public function deletedList(Request $request)
    {
        if (!$this->protect()) {
            return redirect()->route('dashboard');
        }

        $files = Files::orderBy('id', 'desc')
            ->with('folders', 'user');

        if ($request->folder) {
            $files = $files->whereHas('folders', function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->folder . '%');
            });
        }

        if ($request->search) {
            $files = $files->where(function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->user) {
            $files = $files->whereHas('user', function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->user . '%');
            });
        }

        $files->where('status', false);

        $files = $files->paginate((int) $request->limit ?? 20);

        $files->appends($request->all());

        return view('files.deleted', [
            'files' => $files
        ]);
    }

    public function delete(int $id)
    {
        $file = Files::where('id', $id)
            ->first();

        if (!$file) {
            return redirect()->route('files.index')->with('error', 'File not found');
        }

        $url = $file->original_url;
        $domain = 'https://' . parse_url($url, PHP_URL_HOST);

        $response = Http::withHeaders([
            'X-Cross-Key' => env('CROSS_KEY')
        ])
            ->delete($domain . '/api/admin/files/delete/' . $file->id);

        if ($response->status() !== 200) {
            return redirect()->route('files.index')->with('error', 'Failed to delete file');
        }

        return redirect()->route('files.index')->with('success', 'File deleted');
    }

    public function emptyStorage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return redirect()->route('users.index')->with('error', 'Invalid user id');
        }

        $response = Http::withHeaders([
            'X-Cross-Key' => env('CROSS_KEY')
        ])
            ->post(env('CDN_URL') . '/api/admin/files/user/empty', [
                'user_id' => $request->user_id
            ]);

        if ($response->status() !== 200) {
            return redirect()->route('users.view', $request->user_id)->with('error', 'Failed to empty storage');
        }

        return redirect()->route('users.view', $request->user_id)->with('success', 'Storage emptied');
    }

    public function backupStorage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return redirect()->route('users.index')->with('error', 'Invalid user id');
        }

        $files = Files::where([
            'user_id' => $request->user_id,
            'status' => true,
        ])->count();

        if ($files <= 0) {
            return redirect()->route('users.view', $request->user_id)->with('error', 'Storage is empty');
        }

        $response = Http::withHeaders([
            'X-Cross-Key' => env('CROSS_KEY')
        ])
            ->post(env('CDN_URL') . '/api/admin/files/backup', [
                'user_id' => $request->user_id
            ]);

        // return response()->json($response->json());

        if ($response->status() !== 200) {
            return redirect()->route('users.view', $request->user_id)->with('error', 'Failed to backup storage');
        }

        return redirect()->route('users.view', $request->user_id)->with('success', 'Storage success backup');
    }
}
