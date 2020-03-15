<?php

include(dirname(__FILE__) . '/../config/config.inc.php');
require_once(dirname(__FILE__) . '/parser/spreadsheet/src/Bootstrap.php');

class Zizito3
{
    private $db;
    private $id_lang;
    private $id_shop;
    private $currency;
    private $langMapper;

    public function __construct($shop = 3, $lang = 1, $currency = '')
    {
        $this->id_lang  = empty($_GET['lang']) ? (empty($lang) ? 1 : (int)$lang) : (int)$_GET['lang'];
        $this->id_shop  = empty($_GET['shop']) ? (empty($shop) ? 3 : (int)$shop) : (int)$_GET['shop'];
        $this->currency = empty($_GET['currency']) ? $currency : $_GET['currency'];
        if($this->currency == 'BGN' && $this->currency == 'EUR'){
            $this->id_shop = 3;
        }elseif ($this->currency == 'RON'){
            $this->id_shop = 4;
        }

        $this->db = Db::getInstance(_PS_USE_SQL_SLAVE_);

        $this->langMapper = [
            1 => [
                'lang' => 1,
                'currency' => 'BGN',
                'id_currency' => 1,
                'currency2' => 'лв.',
                'dds' => 1.2,
                'id_country' => 236,
            ],
            3 => [
                'lang' => 1,
                'currency' => 'BGN',
                'id_currency' => 1,
                'currency2' => 'лв.',
                'dds' => 1.2,
                'id_country' => 236,
            ],
            4 => [
                'lang' => 4,
                'currency' => 'RON',
                'id_currency' => 3,
                'currency2' => 'lei.',
                'dds' => 1.19,
                'id_country' => 36,
            ],
            6 => [
                'lang' => 4,
                'currency' => 'RON',
                'id_currency' => 3,
                'currency2' => 'lei.',
                'dds' => 1.19,
                'id_country' => 36,
            ],
            5 => [
                'lang' => 3,
                'currency' => '€',
                'id_currency' => 2,
                'currency2' => '€',
                'dds' => 1.24,
                'id_country' => 9,
            ],
            7 => [
                'lang' => 3,
                'currency' => '€',
                'id_currency' => 2,
                'currency2' => '€',
                'dds' => 1.24,
                'id_country' => 9,
            ],
        ];

        $this->generate();
    }

    /**
     * @return array
     */
    private function assembleProducts()
    {
        $query = "
            SELECT p.id_product as pid, 
            p.ean13,
            p.reference,
            ps.price,
            (SELECT quantity FROM " . _DB_PREFIX_ . "stock_available WHERE id_product = p.id_product AND id_product_attribute = 0) as quantity
            FROM " . _DB_PREFIX_ . "product p
            INNER JOIN " . _DB_PREFIX_ . "product_shop ps ON p.id_product = ps.id_product AND ps.id_shop = {$this->id_shop}
            "; //For tests -- LIMIT 10

        $products = $this->db->executeS($query);

        $payload[] = [
            'pid',
            'ean13',
            'price',
            'price_with_vat',
            'quantity',
            'sku',
            'discounted_price_with_vat'
        ];

        foreach ($products as $product) {
            $productId = intval($product['pid']);
            $ean13 = $product['ean13'] ?: '';
            $prodPrice = number_format( $product['price'], 2, '.', '');
            $prodPriceWithVAT = number_format($product['price'] * $this->langMapper[$this->id_shop]['dds'], 2, '.', '');
            $quantity = $product['quantity'] ?: 0;
            $sku = $product['reference'] ?: '';
            $discountedPrice = number_format( $this->getDiscountPrice($productId), 2, '.', '');
            $attributes = $this->getAttributeByPID($productId);

            $cash = '';
            if($this->currency == 'BGN'){
                $cash = ' лв.';
            }elseif ($this->currency == 'RON'){
                $cash = ' lei.';
            }elseif ($this->currency == 'EUR'){
                $cash = ' €';
                // covert BGN to EURO
                $prodPrice          = empty($prodPrice) ? '' : number_format(($prodPrice / 1.95), 2, '.', '');
                $prodPriceWithVAT   = empty($prodPriceWithVAT) ? '' : number_format(($prodPriceWithVAT / 1.95), 2, '.', '');
                $discountedPrice    = empty($discountedPrice) ? '' : number_format(($discountedPrice / 1.95), 2, '.', '');
            }

            if(!empty($prodPrice))
                $prodPrice .= $cash;

            if(!empty($prodPriceWithVAT))
                $prodPriceWithVAT .= $cash;

            if(!empty($discountedPrice))
                $discountedPrice .= $cash;

            if (!empty($attributes)) {
                foreach ($attributes as $attribute) {
                    $payload[] = [
                        $product['pid'],
                        $attribute['ean13'],
                        $prodPrice,
                        $prodPriceWithVAT,
                        $attribute['quantity'] ?: 0,
                        $attribute['reference'],
                        $discountedPrice
                    ];
                }

                continue;
            }

            $payload[] = [
                $productId,
                $ean13,
                $prodPrice,
                $prodPriceWithVAT,
                $quantity,
                $sku,
                $discountedPrice
            ];
        }

        return $payload;
    }

    private function _assembleTextFields()
    {
        $query = "
            SELECT p.id_product as pid, 
            pl.name,
            pl.meta_title,
            pl.description,
            pl.meta_description,
            pl.link_rewrite
            FROM " . _DB_PREFIX_ . "product p
            INNER JOIN " . _DB_PREFIX_ . "product_shop ps ON p.id_product = ps.id_product AND ps.id_shop = {$this->id_shop}
            INNER JOIN " . _DB_PREFIX_ . "product_lang pl ON p.id_product = pl.id_product AND ps.id_shop = pl.id_shop AND pl.id_lang = {$this->id_lang}
            "; //For tests -- LIMIT 10

        $products = $this->db->executeS($query);

        $payload[] = [
            'pid',
            'title',
            'description',
            'meta_title',
            'meta_description'
        ];

        foreach ($products as $product) {
            $productId = intval($product['pid']);
            $productName = $product['name'];
            $productDesc = $product['description'];
            $metaTitle = $product['meta_title'];
            $metaDescription = $product['meta_description'];

            $attributes = $this->getAttributeByPID($productId);

            if (!empty($attributes)) {
                foreach ($attributes as $attribute) {
                    $payload[] = [
                        $product['pid'],
                        $productName,
                        $productDesc,
                        $metaTitle,
                        $metaDescription,
                        $productLink
                    ];
                }

                continue;
            }

            $payload[] = [
                $productId,
                $productName,
                $productDesc,
                $metaTitle,
                $metaDescription,
                $productLink
            ];
        }

        return $payload;
    }

    function getAttributeByPID($productId)
    {
        $query = "
            SELECT p.id_product as pid,
            p.ean13,
            p.reference,
            pl.price,
            (SELECT quantity FROM " . _DB_PREFIX_ . "stock_available WHERE id_product = p.id_product AND id_product_attribute = 0) as quantity
            FROM " . _DB_PREFIX_ . "product_attribute p
            INNER JOIN " . _DB_PREFIX_ . "product_shop pl ON p.id_product = pl.id_product AND pl.id_shop = 1
            WHERE p.id_product = " . $productId . "
            ";

        $products = $this->db->executeS($query);
        return $products;
    }

    private function generate()
    {
        if(empty($this->currency)){
            $products = $this->_assembleTextFields();
        }else{
            $products = $this->assembleProducts();
        }

        if (!empty($products)) {
            $keys = array_shift($products);
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><products></products>');

            foreach ($products as $product) {
                $xml_product = $xml->addChild('product');

                foreach ($product as $k => $v) {
                    if (is_array($v)) {
                        foreach ($v as $arrValue) {
                            $xml_product->addChild($keys[$k], htmlspecialchars($arrValue));
                        }
                    } else {
                        $xml_product->addChild($keys[$k], htmlspecialchars($v));
                    }
                }
            }

            $this->expose($xml->asXML());
        }
    }

    /**
     * @param $xml
     */
    private function expose($xml)
    {
        $filename = _PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . "public" . DIRECTORY_SEPARATOR . 'zizito_' . $this->id_shop . '_' . $this->id_lang . (empty($this->currency) ? '' : '_' . $this->currency . '_') .'.xml';
        exec("rm -f {$filename}");
        file_put_contents($filename, $xml);
    }

    private function getDiscountPrice($pid)
    {
        $id_group = 3; //default

        $price = Product::priceCalculation(
            $this->id_shop,
            $pid,
            null,
            $this->langMapper[$this->id_shop]['id_country'],
            null,
            null,
            $this->langMapper[$this->id_shop]['id_currency'],
            $id_group,
            null,
            true,
            6,
            false,
            true,
            true,
            $specific_price_output,
            false
        );

        if (!$specific_price_output) {
            return '';
        }

        return number_format($price, 2, '.', '');
    }

    private function _getDiscountPrice($pid, &$catalog_price_without_dds)
    {
        $discounts = $this->db->executeS(
            "SELECT *
            FROM " . _DB_PREFIX_ . "specific_price
            WHERE id_product = {$pid}
            AND `to` > NOW() AND `from` < NOW()
            AND id_shop = {$this->id_shop}
        ");

        $discounted_prices = [];
        foreach ($discounts as $item) {
            if ($item['price'] > 0) {
                $catalog_price_without_dds = floatval($item['price']);
            }
            $discount_price = 0;
            if ($item['reduction_type'] == "percentage") {
                if ($item['reduction'] < 1) {
                    $item['reduction'] = floatval($item['reduction']) * 100;
                }
                $discount_price = $catalog_price_without_dds - ($catalog_price_without_dds * ($item['reduction'] / 100));
            } else if ($item['reduction_type'] == "amount") {
                $discount_price = $catalog_price_without_dds - $item['reduction'];
            }
            $discounted_prices[] = $discount_price;
        }

        if (empty($discounted_prices)) {
            return false;
        }

        $original_price = number_format(min($discounted_prices) * $this->dds, 2, '.', '');

        return $original_price;

    }
}

class generateZizitoXmlFeeds
{
    public function __construct()
    {
        $scriptPath = dirname(__FILE__). DIRECTORY_SEPARATOR. 'feed_generator.php';
        $feeds = array();
        $feeds[] = "php {$scriptPath} zizito3 3 1";
        $feeds[] = "php {$scriptPath} zizito3 3 2";
        $feeds[] = "php {$scriptPath} zizito3 5 3";
        $feeds[] = "php {$scriptPath} zizito3 4 4";
        $feeds[] = "php {$scriptPath} zizito3 3 1 BGN";
        $feeds[] = "php {$scriptPath} zizito3 3 1 EUR";
        $feeds[] = "php {$scriptPath} zizito3 4 4 RON";
        foreach($feeds as $feed){
            $processId = exec($feed . " > /dev/null 2>&1 & echo $!;");
            while($this->checkIfProcessRunning($processId)){
                sleep(10);
            }
        }
        echo ("All feeds were processed successfully! XML files are contained inside /public/ directory! "); die;
    }

    private function checkIfProcessRunning($process){

        $return = false;
        if(file_exists("/proc/{$process}")){
            $return = true;
            echo ' ... ';
        }
        return $return;
    }

}
