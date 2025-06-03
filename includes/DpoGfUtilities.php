<?php
require_once 'CountriesArray.php';

class DpoGfUtilities
{
    public function get_country_code($customerCountry)
    {
        $countries = new CountriesArray();

        return $countries->getCountryCode($customerCountry);
    }

    /**
     * @param array $returns
     *
     * @return array
     */
    public function setReturns(array $returns): array
    {
        if (!empty($returns['gf_dpo_group_return'])) {
            $gfs             = explode('&', $returns['gf_dpo_group_return']);
            $returns['hash'] = explode('=', $gfs[1])[1];
            $ids             = explode('=', $gfs[0])[1];

            // Destructure the ID components
            [$returns['form_id'], $returns['lead_id'], $returns['user_id'], $returns['feed_id']] = explode('|', $ids);
        }

        return $returns;
    }

    /**
     * @param mixed $options
     * @param string $product_options
     *
     * @return string
     */
    public function getOptions(mixed $options, string $product_options): string
    {
        if (is_array($options) && !empty($options)) {
            $product_options = ' (';
            foreach ($options as $option) {
                $product_options .= $option['option_name'] . ', ';
            }
            $product_options = substr($product_options, 0, strlen($product_options) - 2) . ')';
        }

        return $product_options;
    }


    /**
     * @param mixed $discounts
     * @param float|int $discount_amt
     * @param string $query_string
     *
     * @return string
     */
    public function getDiscounts(mixed $discounts, float|int $discount_amt, string $query_string): string
    {
        if (is_array($discounts)) {
            foreach ($discounts as $discount) {
                $discount_full = abs($discount['unit_price']) * $discount['quantity'];
                $discount_amt  += $discount_full;
            }
            if ($discount_amt > 0) {
                $query_string .= "&discount_amount_cart={$discount_amt}";
            }
        }

        return $query_string;
    }


    /**
     * @param mixed $options
     * @param int $product_index
     * @param string $query_string
     *
     * @return string
     */
    public function addOptions(mixed $options, int $product_index, string $query_string): string
    {
        if (is_array($options) && !empty($options)) {
            $option_index = 1;
            foreach ($options as $option) {
                $option_label = urlencode($option['field_label']);
                $option_name  = urlencode($option['option_name']);
                $query_string .= "&on{$option_index}_{$product_index}={$option_label}&os{$option_index}_{$product_index}={$option_name}";
                $option_index++;
            }
        }

        return $query_string;
    }
}
