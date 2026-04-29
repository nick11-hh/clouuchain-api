<?php

namespace App\Services\Woocommerce;

use App\Lib\Code;
use App\Models\GoodsSku;
use App\Services\Admin\BaseService;
use Carbon\Carbon;

class ProductService extends BaseService
{
    public $filterRules = [
        'sku_id' => ['=', 'sku'],
        'sku_id,sku_name' => ['like', 'search'],
        // 'created_at'    => ['between', ['after', 'before']],
        // 'updated_at'    => ['between', ['modified_after', 'modified_before']],
    ];

    protected $orderBy = ['id' => 'desc'];

    public function __construct(GoodsSku $model)
    {
        $this->model = $model;
        $this->query = $model->newQuery();
        $this->formData = request()->all();
        $this->setFilterRules();
    }


    public function filter()
    {

        $after = $this->formData['after']??'';
        if (!empty($after)) {
            $strtotime = strtotime($after);
            if($strtotime != false){

                $after = trim(str_replace(['T', 'Z'], ' ', $after));

                $this->query->where('created_at', '>', $after);
            }
        }

        $modifiedAfter = $this->formData['modified_after']??'';
        if (!empty($modifiedAfter)) {
            $strtotime = strtotime($modifiedAfter);
            if($strtotime != false){

                $modifiedAfter = trim(str_replace(['T', 'Z'], ' ', $modifiedAfter));

                $this->query->where('updated_at', '>', $modifiedAfter);
            }
        }

        $exclude = $this->formData['exclude']??'';
        if (!empty($exclude) && is_array($exclude)) {

            $this->query->whereNotIn('id', $exclude);
        }

        $include = $this->formData['include']??'';
        if (!empty($include) && is_array($include)) {
            
            $this->query->whereIn('id', $include);
        }

        $order = $this->formData['order']??'';
        if (!empty($order) && in_array($order, ['asc', 'desc'])) {
            
            $this->query->orderBy('id', $order);
        }

        $category = $this->formData['category']??'';
        if (!empty($category)) {
            
            $this->query->whereHas('goods', function ($query) use ($category) {
                $query->where('category_id', intval($category));
            });
        }

        return $this;
    }


    protected function makeData(GoodsSku $goodsSku, $host)
    {
        $createdTime = Carbon::createFromFormat('Y-m-d H:i:s', $goodsSku->created_at);
        $createdGmtTime = $createdTime->setTimezone('UTC');

        $updatedTime = Carbon::createFromFormat('Y-m-d H:i:s', $goodsSku->updated_at);
        $updatedGmtTime = $updatedTime->setTimezone('UTC');

        $data = [
            'id' => $goodsSku->id,
            'name' => $goodsSku->goods?->goods_name . ' ' . $goodsSku->spec_name,
            'slug' => $goodsSku->goods?->goods_name . ' ' . $goodsSku->spec_name,
            'permalink' => $goodsSku->goods?->purchase_url,
            'date_created' => $goodsSku->created_at->format('Y-m-d\TH:i:s'),
            'date_created_gmt' => $createdGmtTime->format('Y-m-d\TH:i:s'),
            'date_modified' => $goodsSku->updated_at->format('Y-m-d\TH:i:s'),
            'date_modified_gmt' => $updatedGmtTime->format('Y-m-d\TH:i:s'),
            'type' => 'simple',
            'status' => 'publish',
            'featured' => false,
            'catalog_visibility' => 'visible',
            'description' => '',//$goodsSku->goods?->detail,
            'short_description' => $goodsSku->sku_remark,
            'sku' => $goodsSku->sku_id,
            'price' => $goodsSku->quote_price,
            'regular_price' => $goodsSku->quote_price,
            'sale_price' => '',//$goodsSku->quote_price,
            'date_on_sale_from' => null,
            'date_on_sale_from_gmt' => null,
            'date_on_sale_to' => null,
            'date_on_sale_to_gmt' => null,
            'price_html' => '',
            'on_sale' => false,
            'purchasable' => true,
            'total_sales' => '0',
            'virtual' => false,
            'downloadable' => false,
            'downloads' => [],
            'download_limit' => -1,
            'download_expiry' => -1,
            'external_url' => false,
            'button_text' => '',
            'tax_status' => '',
            'tax_class' => '',
            'manage_stock' => '',
            'stock_quantity' => '',
            'stock_status' => 'instock',
            'backorders' => 'no',
            'backorders_allowed' => false,
            'backordered' => false,
            'sold_individually' => false,
            'weight' => $goodsSku->weight,
            'dimensions' => [
                'length' => $goodsSku->length,
                'width' => $goodsSku->width,
                'height' => $goodsSku->height,
            ],
            'shipping_required' => true,
            'shipping_taxable' => true,
            'shipping_class' => '',
            'shipping_class_id' => 0,
            'reviews_allowed' => 'false',
            'average_rating' => '0.00',
            'rating_count' => 0,
            'related_ids' => [],
            'upsell_ids' => [],
            'cross_sell_ids' => [],
            'parent_id' => 0,//$goodsSku->goods?->id,这里的父商品先不显示
            'purchase_note' => '',
            'categories' => [],
            'tags' => [],
            'images' => [],
            'attributes' => [],
            'default_attributes' => [],
            'variations' => [],
            'grouped_products' => [],
            'menu_order' => 0,
            'meta_data' => [],
            'post_password' => '',
            'global_unique_id' => '',
            'brands' => [],
            '_links' => [
                'self' => [[
                    'href' => 'https://' . $host . '/wp-json/wc/v3/products/' . $goodsSku->id 
                ]],
                'collection' => [[
                    'href' => 'https://' . $host . '/wp-json/wc/v3/products'
                ]]
            ]

        ];

        // dd($goodsSku->goods->category->toArray());
        // if($goodsSku->goods?->category){
        //     $data['categories'][] = [
        //         'id' => $goodsSku->goods?->category?->id,
        //         'name' => $goodsSku->goods?->category?->name,
        //         'slug' => $goodsSku->goods?->category?->name,
        //     ];
        // }

        if($goodsSku->spec_info){
            foreach ($goodsSku->spec_info as $key => $value) {
                $data['attributes'][] = [
                    'id' => 0,
                    'name' => $value['name'],
                    'slug' => $value['name'],
                    'options' => [$value['value']],
                ];
            }
        }

        $src = $goodsSku->images ? $goodsSku->images[0] : '';
        $data['images'][] = [
            'id' => $goodsSku->id,
            'src' => $src,
            'date_created' => $goodsSku->created_at->format('Y-m-d\TH:i:s'),
            'date_created_gmt' => $createdGmtTime->format('Y-m-d\TH:i:s'),
            'date_modified' => $goodsSku->updated_at->format('Y-m-d\TH:i:s'),
            'date_modified_gmt' => $updatedGmtTime->format('Y-m-d\TH:i:s'),
        ];        

        return $data;
    }

    public function getProductList()
    {

        $perPage = $this->formData['per_page']??100;
        $perPage = intval($perPage); 

        $this->setFilter()->filter()->setOrderBy();

        $data = $this->query->with('goods')->simplePaginate($perPage);
        if($data->isEmpty()){

            return response()->json([]);
        }

        $newData = [];
        $host = request()->getHost();
        foreach ($data as $key => $goodsSku) {

            $newData[] = $this->makeData($goodsSku, $host);
        }

        return response()->json($newData);
    }


    public function getInfoById($id)
    {
        $order = $this->model::with(['goods'])->find($id);
        if(!$order){
            return response()->json([
                'code' => 'woocommerce_rest_product_invalid_id',
                'message' => "无效的ID。",
                'data' => [
                    'status' => 404
                ]
            ]);
        }

        $host = request()->getHost();
        $newData = $this->makeData($order, $host);

        return response()->json($newData);
    }
}