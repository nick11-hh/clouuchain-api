<?php

namespace App\Services\PlatformShop\Platform\Shopify;


use App\Lib\Code;
use App\Models\ShopModel;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Shopify\Clients\Graphql;
use App\Exceptions\AccidentException;

class GraphQLApi
{

    protected ?Client $client = null;

    protected ShopModel $shop;

    protected array $header = [
        'Content-Type' => 'application/json'
    ];

    public $version = '2025-01';

    public $responseHeaders = [];

    public function __construct($shop)
    {
        $this->shop = $shop;
        $baseUrl = 'https://'.$this->shop->shop_url.'/admin/api/';
        $this->header['X-Shopify-Access-Token'] = $this->shop->access_token;
        $option = [
            'base_uri' => $baseUrl,
            'timeout'  => 60,
            'verify'   => false
        ];
        /*if(config('app.env') == 'local') {
            $option['proxy'] = env('LOCAL_PROXY', 'http://127.0.0.1:7897');
        }*/
        $this->client = new client($option);
    }

    /**
     * 获取产品列表
     * @param array $ids
     * @return mixed
     * @throws Exception
     */
    public function getProductList($params)
    {
        $after = $params['after'] ?? '';
        $queryParams = "first: 250" . ($after ? ", after: \"{$after}\"" : '');
        $query = <<<QUERY
  query {
    products($queryParams) {
      edges {
        node {
          id
          title
          handle
          publishedAt
          options {
            id
            name
            position
            values
          }
          tags
          status
          bodyHtml
          createdAt
          images (first: 250) {
            nodes {
              id
              url
              src
            }
          }
          variants (first: 250) {
            nodes {
              id
              sku
              barcode
              title
              price
              inventoryQuantity
              image {
                id
                url
                src
              },
              selectedOptions {
                name
                value
              }
            }
          }

        }
        cursor
      }
      pageInfo {
        hasNextPage
      }
    }
  }
QUERY;
        $result = $this->query(['query' => $query]);
        if (!empty($result['errors'])) throw new AccidentException('同步产品失败', Code::OPERATE_FAIL);
        $data = $result['data']['products']['edges'] ?? [];
        $products =[];
        foreach ($data as $product) {
            $products[] = $this->convertFormat($product['node'] ?? []);
        }
        $cursor = '';
        if (!empty($result['data']['products']['pageInfo']) && $result['data']['products']['pageInfo']['hasNextPage']) {  // 有下一页
            $cursor = last($data)['cursor'] ?? '';
        }
        return [
            'products' => $products,
            'cursor' => $cursor
        ];
    }


    public function getProductDetail($id)
    {
        $query = <<<QUERY
  query ProductMetafields(\$ownerId: ID!) {
    product(id: \$ownerId) {
      id
      title
      handle
      publishedAt
      productType
      options {
        id
        name
        position
        values
      }
      tags
      status
      bodyHtml
      createdAt
      images (first: 250) {
        nodes {
          id
          url
          src
        }
      }
      variants (first: 250) {
        nodes {
          id
          sku
          barcode
          title
          price
          inventoryQuantity
          image {
            id
            url
            src
          },
          selectedOptions {
            name
            value
          }
        }
      }
    }
  }
QUERY;
        $variables = [
            "ownerId" => "gid://shopify/Product/{$id}",
        ];
        $result = $this->query(['query' => $query, 'variables' => $variables]);
        if (!empty($result['errors'])) return [];
        $product = $result['data']['product'];
        return $this->convertFormat($product);
    }


    public function publishProduct($data, $images)
    {
        $query = <<<QUERY
  mutation createProductMetafields(\$input: ProductInput!, \$media: [CreateMediaInput!]) {
    productCreate(input: \$input, media: \$media) {
      product {
        id
        variants (first: 250) {
        nodes {
            id
            displayName
            title
        }
      }
      }
      userErrors {
        message
        field
      }
    }
  }
QUERY;
        $variables = [
            "media" => $images,
            "input" => $data
        ];
        $result = $this->query(['query' => $query, 'variables' => $variables]);
        if (!empty($result['errors']) || !empty($result['data']['productCreate']['userErrors'] ?? [])) {
            info('刊登失败', [
                'errors' => $result['errors'] ?? [],
                'userErrors' => !empty($result['data']) ? ($result['data']['productCreate']['userErrors'] ?? []) : []
            ]);
            throw new AccidentException('刊登失败', Code::OPERATE_FAIL);
        }
        return $result['data']['productCreate']['product'];
    }

    public function addProductVariants($productId, $variants)
    {
        $query = <<<QUERY
  mutation ProductVariantsCreate(\$productId: ID!, \$variants: [ProductVariantsBulkInput!]!, \$strategy: ProductVariantsBulkCreateStrategy) {
    productVariantsBulkCreate(productId: \$productId, variants: \$variants, strategy: \$strategy) {
      productVariants {
        id
        title
        barcode
        inventoryItem {
          id
        }
        selectedOptions {
          name
          value
        }
      }
      userErrors {
        field
        message
      }
    }
  }
QUERY;
        $variables = [
            "strategy" => "REMOVE_STANDALONE_VARIANT",
            "productId" => $productId,
            "variants" => $variants,
        ];
        $result = $this->query(['query' => $query, 'variables' => $variables]);
        if (!empty($result['errors']) || !empty($result['data']['productVariantsBulkCreate']['userErrors'] ?? [])) {
            info('添加产品变种失败', [
                'errors' => $result['errors'] ?? [],
                'userErrors' => !empty($result['data']) ? ($result['data']['productVariantsBulkCreate']['userErrors'] ?? []) : [],
            ]);
            throw new AccidentException('添加产品变种失败', Code::OPERATE_FAIL);
        }

        return $result['data']['productVariantsBulkCreate']['productVariants'] ?? [];
    }

    public function uploadProductImages($productId, $images)
    {
        $query = <<<QUERY
  mutation productCreateMedia(\$media: [CreateMediaInput!]!, \$productId: ID!) {
    productCreateMedia(media: \$media, productId: \$productId) {
      media {
        id
        alt
        mediaContentType
        status
      }
      mediaUserErrors {
        field
        message
      }
      product {
        id
        title
      }
    }
  }
QUERY;

        $variables = [
            "media" => $images,
            "productId" => $productId
        ];
        $result = $this->query(['query' => $query, 'variables' => $variables]);
        if (!empty($result['errors']) || !empty($result['data']['productCreate']['userErrors'] ?? [])) {
            info('添加产品图片失败', [
                'errors' => $result['errors'] ?? [],
                'userErrors' => !empty($result['data']) ? ($result['data']['productCreate']['userErrors'] ?? []) : []
            ]);
            throw new AccidentException('添加产品图片失败', Code::OPERATE_FAIL);
        }
        return $result['data']['productCreateMedia']['media'][0] ?? '';
    }

    public function getProductVariantImage($variablesId)
    {
        $query = <<<QUERY
  query ProductVariantMetafield(\$ownerId: ID!) {
    productVariant(id: \$ownerId) {
      image {
        id
        url
        src
      }
    }
  }
QUERY;

        $variables = [
            "ownerId" => "gid://shopify/ProductVariant/{$variablesId}",
        ];
        $result = $this->query(['query' => $query, 'variables' => $variables]);
        if (!empty($result['errors'])) return [];
        return $result['data']['productVariant']['image'] ?? [];
    }


    public function getProductImages($productId)
    {
        $query = <<<QUERY
  query ProductMetafield(\$ownerId: ID!) {
    product(id: \$ownerId) {
      media (first: 250) {
        nodes {
          id
          preview {
            image {
              id
              url
              src
            }
          }
        }
      }
      featuredMedia {
        id
        preview {
          image {
            id
            url
            src
          }
        }
      }
    }
  }
QUERY;
        $variables = [
            "ownerId" => "gid://shopify/Product/{$productId}",
        ];
        $result = $this->query(['query' => $query, 'variables' => $variables]);
        if (!empty($result['errors'])) return [];

        return $result['data']['product']['media']['nodes'] ?? [];
    }

    /***---------------------------- 方法 --------------------------------------****/

    /**
     * @param array $data
     * @return mixed
     * @throws Exception
     */
    protected function query(array $data = [])
    {
        $uri = $this->version . '/' . 'graphql.json';
        try {
            $this->responseHeaders = [];
            $option = ['headers' => $this->header,];
            if (is_array($data)) {
                $option['body'] = json_encode($data);
            } else {
                $option['body']  = json_encode([ "query" => $data ]);
            }

            $response = $this->client->post($uri, $option);
            $this->responseHeaders = $response->getHeaders();
            $content = $response->getBody()->getContents();
            return json_decode($content, true);
        } catch (GuzzleException $e) {
            // info('shopify请求失败', ['message' => $e->getMessage(), 'data' => $data, 'uri' => $uri]);
            throw new AccidentException('请求shopify接口失败' . $e->getMessage(), Code::OPERATE_FAIL);
        }
    }

    protected function convertFormat($data)
    {
        if (empty($data)) return [];

        $id = last(explode('/', $data['id']));
        $skuList = [];
        foreach ($data['variants']['nodes'] as $variant) {
            $variantId = last(explode('/', $variant['id']));
            $skuList[] = [
                'id' => $variantId,
                'platform_sku_id' => $variantId,
                'sku' => $variant['sku'] ?: $variant['barcode'],
                'barcode' => $variant['barcode'],
                'title' => $variant['title'],
                'price' => $variant['price'],
                'inventory_quantity' => $variant['inventoryQuantity'],
                'images' => !empty($variant['image']) ? [$variant['image']['src']] : []
            ];
        }
        return [
            'id' => $id,
            'title' => $data['title'],
            'tags' => $data['tags'],
            'status' => $data['status'],
            'options' => $data['options'],
            'images' => $data['images']['nodes'] ?? [],
            'body_html' => $data['bodyHtml'],
            'created_at' => $data['createdAt'],
            'handle' => $data['handle'],
            'variants' => $skuList
        ];
    }

}
