<?php
/*
 * Copyright (c) 2021 DPO Group
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the MIT License
 */

/**
 * Class dpo-group-pay
 *
 * Create and verify DPO Group payment tokens
 */
class dpo_grouppay
{
    /**
     * Constants for test (sandbox) or live sites base URL
     */
    const DPO_Group_URL_TEST = 'https://secure.3gdirectpay.com';
    const DPO_Group_URL_LIVE = 'https://secure.3gdirectpay.com';

    /**
     * @var string DPO_Group payment base URL
     */
    private $dpo_groupUrl;

    /**
     * @var string DPO_Group payment URL
     */
    private $dpo_groupGateway;

    /**
     * @var bool True for test mode
     */
    private $testMode;

    /**
     * @var string Appended to return url so script knows whether test mode or not
     */
    private $testText;

    /**
     * @var string DPO_Group Merchant Token
     */
    private $companyToken;

    /**
     * @var string DPO_Group Service Type
     */
    private $serviceType;

    /**
     * dpo_grouppay constructor.
     * @param $settings array(DPO_GroupMerchantToken, DPO_GroupServiceType, testMode)
     */
    public function __construct( $settings )
    {
        $testMode = isset( $settings['testMode'] ) ? $settings['testMode'] : false;
        if ( (int) $testMode == 1 ) {
            $this->dpo_groupUrl = self::DPO_Group_URL_TEST;
            $this->testMode     = true;
            $this->testText     = 'teston';
        } else {
            $this->dpo_groupUrl = self::DPO_Group_URL_LIVE;
            $this->testMode     = false;
            $this->testText     = 'liveon';
        }
        $this->companyToken = $settings['DPO_GroupMerchantToken'];
        $this->serviceType  = $settings['DPO_GroupServiceType'];
        $this->dpo_groupGateway = $this->dpo_groupUrl . '/payv2.php';

    }

    /**
     * @return mixed|string
     */
    public function getCompanyToken()
    {
        return $this->companyToken;
    }

    /**
     * @return mixed|string
     */
    public function getServiceType()
    {
        return $this->serviceType;
    }

    /**
     * @return string
     */
    public function getDPO_GroupGateway()
    {
        return $this->dpo_groupGateway;
    }

    /**
     * Create a DPO_Group token for payment processing
     * @param $data
     * @return array
     */
    public function createToken( $data )
    {
        $companyToken      = $data['companyToken'];
        $accountType       = $data['accountType'];
        $paymentAmount     = $data['paymentAmount'];
        $paymentCurrency   = $data['paymentCurrency'];
        $customerFirstName = $data['customerFirstName'];
        $customerLastName  = $data['customerLastName'];
        $customerAddress   = $data['customerAddress'];
        $customerCity      = $data['customerCity'];
        $customerCountry   = $this->get_country_code( $data['customerCountry'] );
        $customerPhone     = preg_replace( '/[^0-9]/', '', $data['customerPhone'] );
        $redirectURL       = $data['redirectURL'];
        $backURL           = $data['backUrl'];
        $customerEmail     = $data['customerEmail'];
        $reference         = $data['companyRef'] . '_' . $this->testText;

        $odate   = date( 'Y/m/d H:i' );
        $postXml = <<<POSTXML
        <?xml version="1.0" encoding="utf-8"?> <API3G> <CompanyToken>$companyToken</CompanyToken> <Request>createToken</Request> <Transaction> <PaymentAmount>$paymentAmount</PaymentAmount> <PaymentCurrency>$paymentCurrency</PaymentCurrency> <CompanyRef>$reference</CompanyRef> <customerFirstName>$customerFirstName</customerFirstName> <customerLastName>$customerLastName</customerLastName> <customerAddress>$customerAddress</customerAddress> <customerCity>$customerCity</customerCity> <customerCountry>$customerCountry</customerCountry> <customerPhone>$customerPhone</customerPhone> <RedirectURL>$redirectURL</RedirectURL> <BackURL>$backURL</BackURL> <customerEmail>$customerEmail</customerEmail> <TransactionSource>gravity-forms</TransactionSource> </Transaction> <Services> <Service> <ServiceType>$accountType</ServiceType> <ServiceDescription>$reference</ServiceDescription> <ServiceDate>$odate</ServiceDate> </Service> </Services> </API3G>
POSTXML;

        $curl = curl_init();
        curl_setopt_array( $curl, array(
            CURLOPT_URL            => $this->dpo_groupUrl . "/API/v6/",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => "POST",
            CURLOPT_POSTFIELDS     => $postXml,
            CURLOPT_HTTPHEADER     => array(
                "cache-control: no-cache",
            ),
        ) );

        $responded = false;
        $attempts  = 0;

        //Try up to 10 times to create token
        while ( !$responded && $attempts < 10 ) {
            $error    = null;
            $response = curl_exec( $curl );
            $error    = curl_error( $curl );

            if ( $response != '' ) {
                $responded = true;
            }
            $attempts++;
        }
        curl_close( $curl );

        if ( $error ) {
            return [
                'success' => false,
                'error'   => $error,
            ];
            exit;
        }

        if ( $response != '' ) {
            $xml = new \SimpleXMLElement( $response );

            // Check if token was created successfully
            if ( $xml->xpath( 'Result' )[0] != '000' ) {
                exit();
            } else {
                $transToken        = $xml->xpath( 'TransToken' )[0]->__toString();
                $result            = $xml->xpath( 'Result' )[0]->__toString();
                $resultExplanation = $xml->xpath( 'ResultExplanation' )[0]->__toString();
                $transRef          = $xml->xpath( 'TransRef' )[0]->__toString();

                return [
                    'success'           => true,
                    'result'            => $result,
                    'transToken'        => $transToken,
                    'resultExplanation' => $resultExplanation,
                    'transRef'          => $transRef,
                ];
            }
        } else {
            return [
                'success' => false,
                'error'   => $response,
            ];
            exit;
        }
    }

    /**
     * Verify the DPO_Group token created in first step of transaction
     * @param $data
     * @return bool|string
     * @throws Exception
     */
    public function verifyToken( $data )
    {
        $companyToken = $data['companyToken'];
        $transToken   = $data['transToken'];

        $verified = false;
        $attempts = 0;

        while ( !$verified && $attempts < 10 ) {
            $err  = null;
            $curl = curl_init();
            curl_setopt_array( $curl, array(
                CURLOPT_URL            => $this->dpo_groupUrl . "/API/v6/",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => "",
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => "POST",
                CURLOPT_POSTFIELDS     => "<?xml version=\"1.0\" encoding=\"utf-8\"?>\r\n<API3G>\r\n  <CompanyToken>" . $companyToken . "</CompanyToken>\r\n  <Request>verifyToken</Request>\r\n  <TransactionToken>" . $transToken . "</TransactionToken>\r\n</API3G>",
                CURLOPT_HTTPHEADER     => array(
                    "cache-control: no-cache",
                ),
            ) );

            $response = curl_exec( $curl );
            if ( $response != '' ) {
                $verified = true;
            }
            $err = curl_error( $curl );
            $attempts++;
        }
        curl_close( $curl );

        if ( $err ) {
            return [
                'success' => false,
                'error'   => $err,
            ];
        } else {
            return [
                'success'  => true,
                'response' => $response,
            ];
        }
    }

    public function getDPO_GroupPayHtml( $id )
    {
        return "
          <form action={$this->dpo_groupUrl} method='get' name='dpo_group123'>
               <input name='ID' type='hidden' value='$id' />
          </form>
          <script>
               document.forms['dpo_group'].submit();
          </script>";
    }

    private function get_country_code( $customerCountry )
    {
        include_once 'CountriesArray.php';
        $countries = new CountriesArray();
        return $countries->getCountryCode( $customerCountry );
    }
}
