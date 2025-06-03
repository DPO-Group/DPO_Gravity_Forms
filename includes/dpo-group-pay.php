<?php
/*
 * Copyright (c) 2025 DPO Group
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the MIT License
 */

/**
 * Class dpo-group-pay
 *
 * Create and verify DPO Pay payment tokens
 */
class dpo_grouppay
{
    private function get_country_code($customerCountry)
    {
        include_once 'CountriesArray.php';
        $countries = new CountriesArray();

        return $countries->getCountryCode($customerCountry);
    }
}
