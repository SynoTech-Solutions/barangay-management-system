<?php

namespace App\Http\Controllers\Blotter;

use App\Http\Controllers\Controller;
use App\Http\Requests\Blotter\BlotterPhotoRequest;
use App\Http\Support\Toast;
use App\Models\Blotter;
use App\Models\BlotterPhoto;
use App\Services\Blotter\BlotterPhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BlotterPhotoController extends Controller
{
    private const string CACHE_CONTROL = 'private, no-store';

    public function __construct(private readonly BlotterPhotoService $photos) {}

    public function show(Blotter $blotter, BlotterPhoto $photo): StreamedResponse
    {
        $disk = $this->photos->disk();
        abort_unless($photo->blotter_id === $blotter->id && $disk->exists($photo->path), Response::HTTP_NOT_FOUND);

        return $disk->response($photo->path, null, [
            'Content-Type' => $photo->mime_type,
            'Cache-Control' => self::CACHE_CONTROL,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function store(BlotterPhotoRequest $request, Blotter $blotter): RedirectResponse
    {
        $this->photos->add($blotter, $request->photo(), $this->actor($request));
        Toast::success('messages.blotter_photo_added');

        return back();
    }

    public function destroy(Request $request, Blotter $blotter, BlotterPhoto $photo): RedirectResponse
    {
        abort_unless($photo->blotter_id === $blotter->id, Response::HTTP_NOT_FOUND);
        $this->photos->remove($blotter, $photo, $this->actor($request));
        Toast::success('messages.blotter_photo_removed');

        return back();
    }
}
