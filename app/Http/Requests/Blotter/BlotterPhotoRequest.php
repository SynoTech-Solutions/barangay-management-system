<?php

namespace App\Http\Requests\Blotter;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class BlotterPhotoRequest extends FormRequest
{
    public const int PHOTO_MAX_KILOBYTES = 6144;

    public const int PHOTO_MAX_DIMENSION = 6000;

    private const string ALLOWED_TYPES = 'jpg,jpeg,png,webp';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'photo' => [
                'required',
                'image',
                'mimes:'.self::ALLOWED_TYPES,
                'max:'.self::PHOTO_MAX_KILOBYTES,
                'dimensions:max_width='.self::PHOTO_MAX_DIMENSION.',max_height='.self::PHOTO_MAX_DIMENSION,
            ],
        ];
    }

    public function photo(): UploadedFile
    {
        $photo = $this->file('photo');
        abort_unless($photo instanceof UploadedFile, Response::HTTP_UNPROCESSABLE_ENTITY);

        return $photo;
    }
}
