<?php

namespace App\Http\Controllers\Residents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Residents\ResidentPhotoRequest;
use App\Http\Support\Toast;
use App\Models\Resident;
use App\Services\Residents\ResidentPhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResidentPhotoController extends Controller
{
    private const string CACHE_CONTROL = 'private, max-age=86400';

    public function __construct(private readonly ResidentPhotoService $photos) {}

    public function show(Resident $resident): StreamedResponse
    {
        $disk = $this->photos->disk();
        abort_if($resident->photo_path === null || ! $disk->exists($resident->photo_path), Response::HTTP_NOT_FOUND);

        return $disk->response($resident->photo_path, null, [
            'Cache-Control' => self::CACHE_CONTROL,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function update(ResidentPhotoRequest $request, Resident $resident): RedirectResponse
    {
        $this->photos->replace($resident, $request->photo(), $this->actor($request));

        Toast::success('messages.resident_photo_updated');

        return back();
    }

    public function destroy(Request $request, Resident $resident): RedirectResponse
    {
        $this->photos->remove($resident, $this->actor($request));

        Toast::success('messages.resident_photo_removed');

        return back();
    }
}
