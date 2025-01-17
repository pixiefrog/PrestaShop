<?php
/**
 * 2007-2015 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2015 PrestaShop SA
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

namespace PrestaShopBundle\Model\Product;

use PrestaShop\PrestaShop\Core\Business\Cldr\Repository as cldrRepository;

/**
 * This form class is responsible to map the form data to the product object
 */
class AdminModelAdapter extends \PrestaShopBundle\Model\AdminModelAdapter
{
    private $context;
    private $adminProductWrapper;
    private $cldrRepository;
    private $locales;
    private $defaultLocale;
    private $tools;
    private $productAdapter;
    private $supplierAdapter;
    private $featureAdapter;
    private $packAdapter;
    private $product;
    private $translatableKeys;
    private $unmapKeys;

    /**
     * Constructor
     * Set all adapters needed and get product
     *
     * @param int $id The product ID
     * @param object $container The Sf2 container
     */
    public function __construct($id, $container)
    {
        $this->context = $container->get('prestashop.adapter.legacy.context');
        $this->contextShop = $this->context->getContext();
        $this->adminProductWrapper = $container->get('prestashop.adapter.admin.wrapper.product');
        $this->cldrRepository = new cldrRepository($this->contextShop->language);
        $this->locales = $this->context->getLanguages();
        $this->defaultLocale = $this->locales[0]['id_lang'];
        $this->tools = $container->get('prestashop.adapter.tools');
        $this->productAdapter = $container->get('prestashop.adapter.data_provider.product');
        $this->supplierAdapter = $container->get('prestashop.adapter.data_provider.supplier');
        $this->featureAdapter = $container->get('prestashop.adapter.data_provider.feature');
        $this->packAdapter = $container->get('prestashop.adapter.data_provider.pack');
        $this->product = $id ? $this->productAdapter->getProduct($id, true) : null;
        $this->productPricePriority = $this->adminProductWrapper->getPricePriority($id);
        if ($this->product != null) {
            $this->product->loadStockData();
        }

        //define translatable key
        $this->translatableKeys = array(
            'name',
            'description',
            'description_short',
            'link_rewrite',
            'meta_title',
            'meta_description',
            'available_now',
            'available_later',
        );

        //define unused key for manual binding
        $this->unmapKeys = array('name',
            'description',
            'description_short',
            'images',
            'related_products',
            'categories',
            'suppliers',
            'display_options',
            'features',
            'specific_price',
        );

        //define multishop keys
        $this->multishopKeys = array('category_box',
            'id_category_default',
            'attribute_wholesale_price',
            'attribute_price_impact',
            'attribute_weight_impact',
            'attribute_unit_impact',
            'attribute_ecotax',
            'attribute_minimal_quantity',
            'available_date_attribute',
            'attribute_default',
            'uploadable_files',
            'text_fields',
            'active',
            'redirect_type',
            'id_product_redirected',
            'visibility',
            'available_for_order',
            'show_price',
            'online_only',
            'condition',
            'wholesale_price',
            'price',
            'id_tax_rules_group',
            'ecotax',
            'unit_price',
            'on_sale',
            'minimal_quantity',
            'available_date',
        );
    }

    /**
     * modelMapper
     * Map form data to object model
     *
     * @param array $form_data
     * @param bool $isMultiShopContext If the context is define to multishop, force data to be apply on all shops
     *
     * @return array Transformed form data to model attempt
     */
    public function getModelDatas($form_data, $isMultiShopContext = false)
    {
        //merge all form steps
        $form_data = array_merge(['id_product' => $form_data['id_product']], $form_data['step1'], $form_data['step2'], $form_data['step3'], $form_data['step4'], $form_data['step5'], $form_data['step6']);

        //extract description_short from description
        foreach ($this->locales as $locale) {
            if ($form_data['description'][$locale['id_lang']] && false !== strpos($form_data['description'][$locale['id_lang']], '<p><!-- excerpt --></p>')) {
                $description_full = explode('<p><!-- excerpt --></p>', $form_data['description'][$locale['id_lang']]);
                $form_data['description'][$locale['id_lang']] = isset($description_full[1]) ? $description_full[1] : $description_full[0];
                $form_data['description_short'][$locale['id_lang']] = isset($description_full[1]) ? $description_full[0] : '';
            } else {
                $form_data['description_short'][$locale['id_lang']] = '';
            }
        }

        //map translatable
        foreach ($this->translatableKeys as $field) {
            foreach ($form_data[$field] as $lang_id => $translate_value) {
                $form_data[$field.'_'.$lang_id] = $translate_value;
            }
        }

        //Product type
        if ($form_data['type_product'] == 2) {
            $form_data['is_virtual'] = 1;
        } else {
            $form_data['is_virtual'] = 0;
        }

        //if product is disable, remove rediretion strategy fields
        if ($form_data['active'] == 1) {
            $form_data['redirect_type'] = '';
            $form_data['id_product_redirected'] = 0;
        } else {
            $form_data['redirect_type'] = (string)$form_data['redirect_type'];

            if ($form_data['redirect_type'] != '404') {
                if (isset($form_data['id_product_redirected']) && !empty($form_data['id_product_redirected']['data'])) {
                    $form_data['id_product_redirected'] = $form_data['id_product_redirected']['data'][0];
                } else {
                    $form_data['id_product_redirected'] = 0;
                }
            } else {
                $form_data['id_product_redirected'] = 0;
            }
        }

        //map inputPackItems
        if ($form_data['type_product'] == 1 && !empty($form_data['inputPackItems']) && !empty($form_data['inputPackItems']['data'])) {
            $inputPackItems = '';
            foreach ($form_data['inputPackItems']['data'] as $productIds) {
                $inputPackItems .= $productIds.'-';
            }
            $form_data['inputPackItems'] = $inputPackItems;
        } else {
            $form_data['inputPackItems'] = '';
        }

        //map categories
        foreach ($form_data['categories']['tree'] as $category) {
            $form_data['categoryBox'][] = $category;
        }

        //if empty categories, set default one
        if (empty($form_data['categoryBox'])) {
            $form_data['categoryBox'][] = $this->context->shop->id_category;
        }

        //if default category not define, set the default one
        if (empty($form_data['id_category_default'])) {
            $form_data['id_category_default'] = $this->context->shop->id_category;
        }

        //map suppliers
        $form_data['supplier_loaded'] = 1;
        if (!empty($form_data['id_product']) && !empty($form_data['suppliers'])) {
            foreach ($form_data['suppliers'] as $id_supplier) {
                $form_data['check_supplier_'.$id_supplier] = 1;

                //map supplier combinations
                foreach ($form_data['supplier_combination_'.$id_supplier] as $combination) {
                    $key = $form_data['id_product'].'_'.$combination['id_product_attribute'].'_'.$id_supplier;
                    $form_data['supplier_reference_'.$key] = $combination['supplier_reference'];
                    $form_data['product_price_'.$key] = $combination['product_price'];
                    $form_data['product_price_currency_'.$key] = $combination['product_price_currency'];

                    unset($form_data['supplier_combination_'.$id_supplier]);
                }
            }
        }

        //map display options
        foreach ($form_data['display_options'] as $option => $value) {
            $form_data[$option] = $value;
        }

        //if empty, set link_rewrite for default locale
        if (empty($form_data['link_rewrite_'.$this->locales[0]['id_lang']])) {
            $form_data['link_rewrite_'.$this->locales[0]['id_lang']] = $this->tools->link_rewrite($form_data['name_'.$this->locales[0]['id_lang']]);
        }

        //map inputAccessories
        if (!empty($form_data['related_products']) && !empty($form_data['related_products']['data'])) {
            $inputAccessories = '';
            foreach ($form_data['related_products']['data'] as $accessoryIds) {
                $accessoryIds = explode(',', $accessoryIds);
                $inputAccessories .= $accessoryIds[0].'-';
            }
            $form_data['inputAccessories'] = $inputAccessories;
        }

        //map features
        if (!empty($form_data['features'])) {
            foreach ($form_data['features'] as $dataFeature) {
                $idFeature = $dataFeature['feature'];

                //custom value is defined
                if ($dataFeature['custom_value'][$this->defaultLocale]) {
                    foreach ($this->locales as $locale) {
                        $form_data['feature_'.$idFeature.'_value'] = null;
                        $form_data['custom_'.$idFeature.'_'.$locale['id_lang']] = $dataFeature['custom_value'][$locale['id_lang']];
                    }
                } elseif ($dataFeature['value']) {
                    $form_data['feature_'.$idFeature.'_value'] = $dataFeature['value'];
                }
            }
        }

        //add some legacy filed to execute some add/update methods
        $form_data['submitted_tabs'] = ['Shipping'];

        //map all
        $new_form_data = [];
        foreach ($form_data as $k => $v) {
            if (in_array($k, $this->unmapKeys) || in_array($k, $this->translatableKeys)) {
                continue;
            }
            $new_form_data[$k] = $v;
        }

        //map specific price priority
        $new_form_data['specificPricePriority'] = [
            $new_form_data['specificPricePriority_0'],
            $new_form_data['specificPricePriority_1'],
            $new_form_data['specificPricePriority_2'],
            $new_form_data['specificPricePriority_3'],
        ];

        $new_form_data = array_merge(parent::getHookData(), $new_form_data);

        //if multishop context is define, simalte multishop checkbox for all POST DATAS
        if ($isMultiShopContext) {
            foreach ($this->multishopKeys as $multishopKey) {
                $new_form_data['multishop_check'][$multishopKey] = 1;
            }

            //apply multishop rules for translatables fields
            foreach ($this->translatableKeys as $field) {
                foreach ($form_data[$field] as $lang_id => $translate_value) {
                    $new_form_data['multishop_check'][$field][$lang_id] = 1;
                }
            }
        }

        return $new_form_data;
    }

    /**
     * formMapper
     * Map object model to form data
     *
     * @return array Transformed model data to form attempt
     */
    public function getFormDatas()
    {
        $default_data = [
            'id_product' => 0,
            'step1' => [
                'type_product' => 0,
                'condition' => 'new',
                'active' => 0,
                'price_shortcut' => 0,
                'qty_0_shortcut' => 0,
                'categories' => ['tree' => [$this->contextShop->shop->id_category]]
            ],
            'step2' => [
                'id_tax_rules_group' => $this->productAdapter->getIdTaxRulesGroup(),
                'price' => 0,
                'specific_price' => [
                    'sp_from_quantity' => 1,
                    'sp_reduction' => 0,
                    'sp_reduction_tax' => 1,
                    'leave_bprice' => true,
                ],
                'specificPricePriority_0' => $this->productPricePriority[0],
                'specificPricePriority_1' => $this->productPricePriority[1],
                'specificPricePriority_2' => $this->productPricePriority[2],
                'specificPricePriority_3' => $this->productPricePriority[3],
                'specificPricePriorityToAll' => false,
            ],
            'step3' => [
                'qty_0' => 0,
                'out_of_stock' => 2,
                'minimal_quantity' => 1,
                'available_date' => '0000-00-00',
                'pack_stock_type' => 3,
            ],
            'step4' => [
                'width' => 0,
                'height' => 0,
                'depth' => 0,
                'weight' => 0,
                'additional_shipping_cost' => 0,
            ],
            'step6' => [
                'redirect_type' => '404',
                'visibility' => 'both',
                'display_options' => [
                    'available_for_order' => true,
                    'show_price' => true,
                ],
            ]
        ];

        //if empty model_data, return the default value object
        if (!$this->product) {
            return $default_data;
        }

        $form_data = [
            'id_product' => $this->product->id,
            'step1' => [
                'type_product' => $this->product->getType(),
                'inputPackItems' => [
                    'data' => array_map(
                        function ($p) {
                            return [
                                "id" => $p->id,
                                "id_product_attribute" => isset($p->id_pack_product_attribute) ? $p->id_pack_product_attribute : 0,
                                "name" => $p->name,
                                "ref" => $p->reference,
                                "quantity" => $p->pack_quantity,
                            ];
                        },
                        $this->packAdapter->getItems($this->product->id, $this->locales[0]['id_lang'])
                    )
                ],
                'name' => $this->product->name,
                'description' => $this->getFormFullDescription($this->product->description, $this->product->description_short),
                //images
                'upc' => $this->product->upc,
                'ean13' => $this->product->ean13,
                'isbn' => $this->product->isbn,
                'reference' => $this->product->reference,
                'condition' => $this->product->condition,
                'active' => $this->product->active,
                'price_shortcut' => $this->product->price,
                'qty_0_shortcut' => $this->product->getQuantity($this->product->id),
                'categories' => ['tree' => $this->product->getCategories()],
                'id_category_default' => $this->product->id_category_default,
                'id_manufacturer' => $this->product->id_manufacturer,
                'related_products' => [
                    'data' => array_map(
                        function ($p) {
                            return($p['id_product']);
                        },
                        call_user_func_array(
                            array($this->product, "getAccessoriesLight"),
                            array($this->locales[0]['id_lang'], $this->product->id)
                        )
                    )
                ],
                'features' => $this->getFormFeatures()
            ],
            'step2' => [
                'price' => $this->product->price,
                'id_tax_rules_group' => $this->product->id_tax_rules_group,
                'on_sale' => (bool) $this->product->on_sale,
                'wholesale_price' => $this->product->wholesale_price,
                'unit_price' => $this->product->unit_price_ratio != 0  ? $this->product->price / $this->product->unit_price_ratio : 0,
                'unity' => $this->product->unity,
                'specific_price' => [
                    'sp_from_quantity' => 1,
                    'sp_reduction' => 0,
                    'sp_reduction_tax' => 1,
                    'leave_bprice' => true,
                ],
                'specificPricePriority_0' => $this->productPricePriority[0],
                'specificPricePriority_1' => $this->productPricePriority[1],
                'specificPricePriority_2' => $this->productPricePriority[2],
                'specificPricePriority_3' => $this->productPricePriority[3],
            ],
            'step3' => [
                'advanced_stock_management' => (bool) $this->product->advanced_stock_management,
                'depends_on_stock' => $this->product->depends_on_stock?"1":"0",
                'qty_0' => $this->product->getQuantity($this->product->id),
                'combinations' => $this->getFormCombinations(),
                'out_of_stock' => $this->product->out_of_stock,
                'minimal_quantity' => $this->product->minimal_quantity,
                'available_now' => $this->product->available_now,
                'available_later' => $this->product->available_later,
                'available_date' => $this->product->available_date,
                'pack_stock_type' => $this->product->pack_stock_type,
            ],
            'step4' => [
                'width' => $this->product->width,
                'height' => $this->product->height,
                'depth' => $this->product->depth,
                'weight' => $this->product->weight,
                'additional_shipping_cost' => $this->product->additional_shipping_cost,
                'selectedCarriers' => $this->getFormProductCarriers()
            ],
            'step5' => [
                'link_rewrite' => $this->product->link_rewrite,
                'meta_title' => $this->product->meta_title,
                'meta_description' => $this->product->meta_description,
            ],
            'step6' => [
                'redirect_type' => $this->product->redirect_type,
                'id_product_redirected' => [
                    'data' => [$this->product->id_product_redirected]
                ],
                'visibility' => $this->product->visibility,
                'display_options' => [
                    'available_for_order' => (bool) $this->product->available_for_order,
                    'show_price' => (bool) $this->product->show_price,
                    'online_only' => (bool) $this->product->online_only,
                ],
                'suppliers' => array_map(
                    function ($s) {
                        return($s->id_supplier);
                    },
                    $this->supplierAdapter->getProductSuppliers($this->product->id)
                ),
                'default_supplier' => $this->product->id_supplier
            ]
        ];

        //Inject data form for supplier combinations
        $form_data['step6'] = array_merge($form_data['step6'], $this->getDataSuppliersCombinations());

        return $form_data;
    }

    /**
     * Generate form supplier/combinations references
     *
     * @return array filled datas form references combinations
     */
    private function getDataSuppliersCombinations()
    {
        $combinations = $this->product->getAttributesResume($this->locales[0]['id_lang']);
        if (!$combinations || empty($combinations)) {
            $combinations[] = array(
                'id_product' => $this->product->id,
                'id_product_attribute' => 0,
                'attribute_designation' => $this->product->name[$this->locales[0]['id_lang']]
            );
        }

        //for each supplier, generate combinations list
        $datasSuppliersCombinations = [];

        foreach ($this->supplierAdapter->getProductSuppliers($this->product->id) as $supplier) {
            foreach ($combinations as $combination) {
                $productSupplierData = $this->supplierAdapter->getProductSupplierData($this->product->id, $combination['id_product_attribute'], $supplier->id_supplier);
                $datasSuppliersCombinations['supplier_combination_'.$supplier->id_supplier][] = [
                    'label' => $combination['attribute_designation'],
                    'supplier_reference' => isset($productSupplierData['product_supplier_reference']) ? $productSupplierData['product_supplier_reference'] : '',
                    'product_price' => isset($productSupplierData['price']) ? $productSupplierData['price'] : 0,
                    'product_price_currency' => isset($productSupplierData['id_currency']) ? $productSupplierData['id_currency'] : 1,
                    'supplier_id' => $supplier->id_supplier,
                    'product_id' => $this->product->id,
                    'id_product_attribute' => $combination['id_product_attribute'],
                ];
            }
        }

        return $datasSuppliersCombinations;
    }

    /**
     * get form Full product Description with description short
     * Map object model to form data
     *
     * @param array $descriptionLangs the translated descriptions
     * @param array $descriptionShortLangs the translated short descriptions
     *
     * @return array full translated description with excerpt tag
     */
    private function getFormFullDescription(array $descriptionLangs, array $descriptionShortLangs)
    {
        $full_descriptions = [];

        foreach ($this->locales as $locale) {
            if (strlen($descriptionShortLangs[$locale['id_lang']]) > 0) {
                $full_descriptions[$locale['id_lang']] =
                    $descriptionShortLangs[$locale['id_lang']].
                    '<p><!-- excerpt --></p>'.
                    $descriptionLangs[$locale['id_lang']];
            } else {
                $full_descriptions[$locale['id_lang']] = $descriptionLangs[$locale['id_lang']];
            }
        }

        return $full_descriptions;
    }

    /**
     * get form product features
     *
     * @return array features with translation
     */
    private function getFormFeatures()
    {
        $formDataFeatures = [];
        foreach ($this->product->getFeatures() as $dataFeature) {
            $itemForm = [
                'feature' => $dataFeature['id_feature'],
                'value' => $dataFeature['id_feature_value'],
                'custom_value' => null,
            ];

            if ($dataFeature['custom'] == 1) {
                $cusomLangs = [];
                foreach ($this->featureAdapter->getFeatureValueLang($dataFeature['id_feature_value']) as $customValues) {
                    $cusomLangs[$customValues['id_lang']] = $customValues['value'];
                }
                $itemForm['custom_value'] = $cusomLangs;
            }

            $formDataFeatures[] = $itemForm;
        }

        return $formDataFeatures;
    }

    /**
     * get product carrier
     *
     * @return array carrier
     */
    private function getFormProductCarriers()
    {
        $formDataCarriers = [];
        foreach ($this->product->getCarriers() as $carrier) {
            $formDataCarriers[] = $carrier['id_carrier'];
        }

        return $formDataCarriers;
    }

    /**
     * Get all product combinations values
     *
     * @return array combinations
     */
    private function getFormCombinations()
    {
        $combinations = $this->product->getAttributeCombinations(1, false);
        $formCombinations = [];
        foreach ($combinations as $combination) {
            $formCombinations[] = $this->getFormCombination($combination);
        }

        return $formCombinations;
    }

    /**
     * Get a combination values
     *
     * @param array $combination The combination values
     *
     * @return array combinations
     */
    public function getFormCombination($combination)
    {
        $attribute_price_impact = 0;
        if ($combination['price'] > 0) {
            $attribute_price_impact = 1;
        } elseif ($combination['price'] < 0) {
            $attribute_price_impact = -1;
        }

        $attribute_weight_impact = 0;
        if ($combination['weight'] > 0) {
            $attribute_weight_impact = 1;
        } elseif ($combination['weight'] < 0) {
            $attribute_weight_impact = -1;
        }

        $attribute_unity_price_impact = 0;
        if ($combination['unit_price_impact'] > 0) {
            $attribute_unity_price_impact = 1;
        } elseif ($combination['unit_price_impact'] < 0) {
            $attribute_unity_price_impact = -1;
        }

        //generate combination name
        $attributesCombinations = $this->product->getAttributeCombinationsById($combination['id_product_attribute'], 1);
        $name = [];
        foreach ($attributesCombinations as $attribute) {
            $name[] = $attribute['group_name'].' - '.$attribute['attribute_name'];
        }

        return [
            'id_product_attribute' => $combination['id_product_attribute'],
            'attributes' => array($combination['group_name'], $combination['attribute_name'], $combination['id_attribute']),
            'attribute_reference' => $combination['reference'],
            'attribute_ean13' => $combination['ean13'],
            'attribute_isbn' => $combination['isbn'],
            'attribute_upc' => $combination['upc'],
            'attribute_wholesale_price' => $combination['wholesale_price'],
            'attribute_price_impact' => $attribute_price_impact,
            'attribute_price' => $combination['price'],
            'attribute_price_display' => $this->cldrRepository->getPrice($combination['price'], $this->contextShop->currency->iso_code),
            'attribute_priceTI' => '',
            'attribute_weight_impact' => $attribute_weight_impact,
            'attribute_weight' => $combination['weight'],
            'attribute_unit_impact' => $attribute_unity_price_impact,
            'attribute_unity' => $combination['unit_price_impact'],
            'attribute_minimal_quantity' => $combination['minimal_quantity'],
            'available_date_attribute' =>  $combination['available_date'],
            'attribute_default' => (bool)$combination['default_on'],
            'attribute_quantity' => $this->productAdapter->getQuantity($this->product->id, $combination['id_product_attribute']),
            'name' => implode(', ', $name)
        ];
    }
}
