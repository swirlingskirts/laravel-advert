<?php
namespace Adumskis\LaravelAdvert;


use Adumskis\LaravelAdvert\Model\Advert;
use Adumskis\LaravelAdvert\Model\AdvertCategory;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;

class AdvertManager {

    /**
     * @var array
     */
    private $used = [];

    /**
     * @var object;
     */
    private static $instance;

    /**
     * @return AdvertManager
     */
    public static function getInstance()
    {
        return static::$instance ?: (static::$instance = new self());
    }


    /**
     * Search advert by AdvertCategory type
     * If duplicate set to true then it's possible that advert will be the same with
     * previous showed advert
     *
     * @param $type
     * @param bool $duplicate
     * @return HtmlString|string
     */
    public function getHTML($type, $duplicate = false){
        $advert_category = AdvertCategory::where('type', $type)->first();
        if(!$advert_category){
            return '';
        }

        $advert = $advert_category
            ->adverts()
            ->where('active', true)
            ->whereNotNull('image_path')
            ->where(function($query) use ($duplicate){
                if(!$duplicate){
                    $query->whereNotIn('id', $this->used);
                }
            })->where(function ($query) {
                  $query->where('start_run', '>=', \DB::raw('NOW()'))
                      ->orWhereNull('start_run');
              })
            ->where(function ($query) {
                  $query->where('end_run', '<=', \DB::raw('NOW()'))
                      ->orWhereNull('end_run');
              })
            ->active()
            ->orderBy('viewed_at', 'ASC')
            ->first();

        if($advert){
            $advert->plusViews();
            $advert->updateLastViewed();
            $this->used[$type][] = $advert->id;
            $html = View::make('partials.advert', compact('advert'))->render();
            return new HtmlString($html);
        } else {
            return '';
        }
    }

}
