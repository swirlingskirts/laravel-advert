<?php

namespace Adumskis\LaravelAdvert\Model;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Facades\Image;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Adumskis\LaravelAdvert\Model\AdvertCategory;
use Illuminate\Support\Str;

class Advert extends Model
{
    protected $fillable = [
        'alt',
        'url',
        'image_url',
        'image_path',
        'views',
        'clicks',
        'active',
        'advert_category_id'
    ];

    protected $dates = ['viewed_at'];

    /**
     * @param array $data
     * @param UploadedFile $image
     * @return Advert
     * @throws \Exception
     */
    public static function make(array $data, UploadedFile $image = null){
        if(!$image){
            throw new \Exception('UploadedFile required');
        }

        $validator = Validator::make(
            $data,
            [
                'url' => 'required',
                'active' => 'boolean',
                'advert_category_id' => 'required|exists:advert_categories,id'
            ]
        );

        if ($validator->fails())
        {
            throw new \Exception($validator->messages()->first());
        }

        $advert = Advert::create($data);
        $advert->saveImage($image);

        return $advert;
    }

    /**
     * @param $query
     * @return mixed
     */
    public function scopeActive($query){
        return $query->where('active', true);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function advert_category(){
        return $this->belongsTo(AdvertCategory::class);
    }

    /**
     * @return bool
     */
    public function activate(){
        return $this->update(['active' => true]);
    }

    /**
     * @return bool
     */
    public function deactivate(){
        return $this->update(['active' => false]);
    }

    /**
     * @return bool
     */
    public function plusViews(){
        return $this->update(['views' => $this->views+1]);
    }

    /**
     * @return bool
     */
    public function plusClicks(){
        return $this->update(['clicks' => $this->clicks+1]);
    }

    /**
     * @return bool
     */
    public function resetViews(){
        return $this->update(['views' => 0]);
    }

    /**
     * @return bool
     */
    public function resetClicks(){
        return $this->update(['clicks' => 0]);
    }

    /**
     * @return bool
     */
    public function updateLastViewed(){
        $this->viewed_at = Carbon::now();
        return $this->save();
    }

    /**
     * @param string $extension
     * @return string
     */
    public static function generateImageName($extension = 'png'){
        return Carbon::now()->timestamp.'_'.Str::random(8).'.'.$extension;
    }


    /**
     * @param UploadedFile $file
     */
    public function saveImage(UploadedFile $file){
        $this->deleteImage();
        $image = Image::make($file);
        $image_name = Advert::generateImageName();
        $advert_category = $this->advert_category;
        $width = $advert_category->width?$advert_category->width:null;
        $height = $advert_category->height?$advert_category->height:null;
        if($advert_category->width === null || $advert_category->height === null){
            $image->resize($advert_category->width, $advert_category->height, function ($constraint) {
                $constraint->aspectRatio();
            });
        } else {
            $image->resize($advert_category->width, $advert_category->height);
        }

        Storage::disk(config('laravel-advert.default_file_system'))->put(config('laravel-advert.upload_path').'/'. $image_name, $image->stream()->__toString(), 'public');

        $this->update([
            'image_url' => config('laravel-advert.upload_path').'/'.$image_name,
            'image_path' => config('laravel-advert.upload_path').'/'.$image_name
        ]);
    }

    /**
     * @throws \Exception
     */
    public function delete(){
        $this->deleteImage();
        parent::delete();
    }

    /**
     *
     */
    private function deleteImage(){
        $storage = Storage::disk(config('laravel-advert.default_file_system'));

        if($this->image_path !== null && $storage->exists($this->image_path)){
            $storage->delete($this->image_path);
        }
    }

    /**
     * @return \Illuminate\Contracts\Routing\UrlGenerator|string
     */
    public function getURL(){
        return url('a/'.$this->id);
    }


    /**
     * @return \Illuminate\Contracts\Routing\UrlGenerator|string
     */
    public function getImageUrl(){
        return url(Storage::disk(config('laravel-advert.default_file_system'))
            ->url($this->image_path));
    }
}
