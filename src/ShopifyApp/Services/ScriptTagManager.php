<?php

namespace Centire\ShopifyApp\Services;

use Centire\ShopifyApp\BasicShopifyAPI;
use Illuminate\Support\Facades\Config;
use App\Shop;

/**
 * Responsible for managing script tags.
 */
class ScriptTagManager
{
    /**
     * The shop.
     *
     * @var Shop
     */
    protected $shop;

    /**
     * The shop API.
     *
     * @var BasicShopifyAPI
     */
    protected $api;

    /**
     * Cached shop script tags result.
     *
     * @var array
     */
    protected $shopScriptTags;

    /**
     * Create a new instance.
     *
     * @param object $shop The shop object
     *
     * @return void
     */
    public function __construct($shop)
    {
        $this->shop = $shop;
        $this->api = $this->shop->api();
    }

    /**
     * Gets the script tags present in the shop.
     *
     * @return array
     */
    public function shopScriptTags()
    {
        if (!$this->shopScriptTags) {
            $this->shopScriptTags = $this->api->graph('
query 
{
    scriptTags(first: 3) 
    {
        edges 
        {
            node 
            {
                id
                src
                displayScope 
            }
        }
    }
}'
            )->body->scriptTags->edges;
        }

        return $this->shopScriptTags;
    }

    /**
     * Gets the script tags present in the configuration.
     *
     * @return array
     */
    public function configScriptTags()
    {
        $scriptTags = config('shopify.script_tags');

        return array_map(function ($scriptTag) {
            return ['src' => $scriptTag['src'], 'displayScope' => strtoupper($scriptTag['display_scope'])];
        }, $scriptTags);
    }

    /**
     * Check if script tag is in the shop (by src).
     *
     * @param array $scriptTag The Script tag
     *
     * @return bool
     */
    public function scriptTagExists(array $scriptTag)
    {
        $shopScriptTags = $this->shopScriptTags();
        foreach ($shopScriptTags as $shopScriptTag) {
            if ($shopScriptTag->node->src === $scriptTag['src']) {
                // Found the script tag in our list
                return true;
            }
        }
        return false;
    }

    /**
     * Creates script tag (if they do not exist).
     *
     * @return array
     */
    public function createScriptTags()
    {
        $configScriptTags = $this->configScriptTags();

        // Create if it does not exist
        $created = [];
        $validScriptTags = [];

        $mutation = [];
        $mutationArgs = [];
        $mutationVariables = [];
        foreach ($configScriptTags as $i => $scriptTag) {
            // Check if the required script tag exists on the shop
            if (!$this->scriptTagExists($scriptTag)) {
                $mutationArgs[] = '$input_' . $i . ': ScriptTagInput!';
                $mutation['SCRIPT_TAG_' . $i] = '
SCRIPT_TAG_' . $i . ': scriptTagCreate(input: $input_' . $i . ') 
{
    userErrors 
    {
        field
        message
    }
    scriptTag 
    {
        id
        src
        displayScope
    }
}';

                $mutationVariables['input_' . $i] =array_only( $scriptTag, ['src', 'displayScope'] );
            }

            $validScriptTags[] = $scriptTag['src'];
        }

        if ($mutation) {
            $scriptTags = $this->api->graph('mutation scriptTagCreate(' . implode(',', $mutationArgs) . '){' . implode('', $mutation) . '}', $mutationVariables)->body;

            foreach ($mutation as $key => $value) {
                if (isset($scriptTags->$key) && $scriptTags->$key->scriptTag->id) {
                    $created[] = $scriptTags->$key->scriptTag;
                }
            }
        }

        // Delete
        $invalidScriptTags = [];
        foreach ($this->shopScriptTags as $scriptTag) {
            if (!in_array($scriptTag->node->src, $validScriptTags)) {
                $invalidScriptTags[] = $scriptTag;
            }
        }

        if($invalidScriptTags){
            $this->deleteScriptTags($invalidScriptTags);
        }

        return $created;
    }

    /**
     * Deletes script tag(s) in the shop tied to the app.
     *
     * @param $scriptTags array Script tags to delete
     * @return array
     */
    public function deleteScriptTags($scriptTags = [])
    {
        $shopScriptTags = $scriptTags ? $scriptTags : $this->shopScriptTags();
        $deleted = [];
        $mutation = [];
        $mutationArgs = [];
        $mutationVariables = [];

        foreach ($shopScriptTags as $i => $shopScriptTag) {
            $mutationArgs[] = '$id_' . $i . ': ID!';
            $mutation['SCRIPT_TAG_' . $i] = '
SCRIPT_TAG_' . $i . ': scriptTagDelete(id: $id_' . $i . ') 
{
    deletedScriptTagId
    userErrors {
        field
        message
    }
}';
            $mutationVariables['id_' . $i] = $shopScriptTag->node->id;
        }

        if ($mutation) {
            $response = $this->api->graph('mutation scriptTagDelete(' . implode(',', $mutationArgs) . '){' . implode('', $mutation) . '}', $mutationVariables)->body;

            foreach ($mutation as $key => $value) {
                if (isset($response->$key) && $response->$key->deletedScriptTagId) {
                    $deleted[] = $response->$key->deletedScriptTagId;
                }
            }
        }

        // Reset
        if($this->shopScriptTags){
            foreach($this->shopScriptTags as $key => $scriptTag){
                if (in_array($scriptTag->node->id, $deleted)) {
                    unset($this->shopScriptTags[$key]);
                }
            }
        }

        return $deleted;
    }

    /**
     * Recreates the script tags.
     *
     * @return void
     */
    public function recreateScriptTags()
    {
        $this->deleteScriptTags();
        $this->createScriptTags();
    }
}