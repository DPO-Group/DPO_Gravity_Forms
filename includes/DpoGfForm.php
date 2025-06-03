<?php

class DpoGfForm
{
    /**
     * Retrieve DPO form fields.
     *
     * @return array
     */
    public static function getFields(): array
    {
        return array(
            array(
                'name'     => 'DPO_GroupMerchantToken',
                'label'    => __('DPO Pay Company Token ', 'gravity-forms-dpo-group'),
                'type'     => 'text',
                'class'    => 'medium',
                'required' => true,
                'tooltip'  => constant('H6_TAG') . __('DPO Pay Company Token', 'gravity-forms-dpo-group') . constant(
                        'H6_TAG_CLOSING'
                    ) . __('Enter your DPO Pay Company Token.', 'gravity-forms-dpo-group'),
            ),
            array(
                'name'     => 'DPO_GroupServiceType',
                'label'    => __('Service Type', 'gravity-forms-dpo-group'),
                'type'     => 'text',
                'class'    => 'medium',
                'required' => true,
                'tooltip'  => constant('H6_TAG') . __('DPO Pay Service Type', 'gravity-forms-dpo-group') . constant(
                        'H6_TAG_CLOSING'
                    ) . __('Enter your DPO Pay Service Type.', 'gravity-forms-dpo-group'),
            ),
            array(
                'name'          => 'useCustomConfirmationPage',
                'label'         => __('Use Custom Confirmation Page', 'gravity-forms-dpo-group'),
                'type'          => 'radio',
                'choices'       => array(
                    array(
                        'id'    => 'gf_dpo_group_thankyou_yes',
                        'label' => __('Yes', 'gravity-forms-dpo-group'),
                        'value' => 'yes',
                    ),
                    array(
                        'id'    => 'gf_dpo_group_thakyou_no',
                        'label' => __('No', 'gravity-forms-dpo-group'),
                        'value' => 'no',
                    ),
                ),
                'horizontal'    => true,
                'default_value' => 'yes',
                'tooltip'       => constant('H6_TAG') . __(
                        'Use Custom Confirmation Page',
                        'gravity-forms-dpo-group'
                    ) . constant('H6_TAG_CLOSING') . __(
                                       'Select Yes to display custom confirmation thank you page to the user.',
                                       'gravity-forms-dpo-group'
                                   ),
            ),
            array(
                'name'    => 'successPageUrl',
                'label'   => __('Successful Page Url', 'gravity-forms-dpo-group'),
                'type'    => 'text',
                'class'   => 'medium',
                'tooltip' => constant('H6_TAG') . __('Successful Page Url', 'gravity-forms-dpo-group') . constant(
                        'H6_TAG_CLOSING'
                    ) . __('Enter a thank you page url when a transaction is successful.', 'gravity-forms-dpo-group'),
            ),
            array(
                'name'    => 'failedPageUrl',
                'label'   => __('Failed Page Url', 'gravity-forms-dpo-group'),
                'type'    => 'text',
                'class'   => 'medium',
                'tooltip' => constant('H6_TAG') . __('Failed Page Url', 'gravity-forms-dpo-group') . constant(
                        'H6_TAG_CLOSING'
                    ) . __('Enter a thank you page url when a transaction is failed.', 'gravity-forms-dpo-group'),
            ),
            array(
                'name'          => 'mode',
                'label'         => __('Mode', 'gravity-forms-dpo-group'),
                'type'          => 'radio',
                'choices'       => array(
                    array(
                        'id'    => 'gf_dpo_group_mode_production',
                        'label' => __('Production', 'gravity-forms-dpo-group'),
                        'value' => 'production',
                    ),
                    array(
                        'id'    => 'gf_dpo_group_mode_test',
                        'label' => __('Test', 'gravity-forms-dpo-group'),
                        'value' => 'test',
                    ),
                ),
                'horizontal'    => true,
                'default_value' => 'production',
                'tooltip'       => constant('H6_TAG') . __('Mode', 'gravity-forms-dpo-group') . constant(
                        'H6_TAG_CLOSING'
                    ) . __(
                                       'Select Production to enable live transactions. Select Test for testing with the dummy accounts.',
                                       'gravity-forms-dpo-group'
                                   ),
            ),
        );
    }

    public function getCancelUrl(): array
    {
        return array(
            array(
                'name'     => 'continueText',
                'label'    => __('Continue Button Label', 'gravity-forms-dpo-group'),
                'type'     => 'text',
                'class'    => 'medium',
                'required' => false,
                'tooltip'  => '<h6>' . __('Continue Button Label', 'gravity-forms-dpo-group') . '</h6>' . __(
                        'Enter the text that should appear on the continue button once payment has been completed via DPO Pay.',
                        'gravity-forms-dpo-group'
                    ),
            ),
            array(
                'name'     => 'cancelUrl',
                'label'    => __('Cancel URL', 'gravity-forms-dpo-group'),
                'type'     => 'text',
                'class'    => 'medium',
                'required' => false,
                'tooltip'  => '<h6>' . __('Cancel URL', 'gravity-forms-dpo-group') . '</h6>' . __(
                        'Enter the URL the user should be sent to should they cancel before completing their payment. It currently defaults to the DPO Pay website.',
                        'gravity-forms-dpo-group'
                    ),
            ),
        );
    }

    /**
     * @return array
     */
    public function getPostSettings(): array
    {
        return array(
            'name'    => 'post_checkboxes',
            'label'   => __('Posts', 'gravity-forms-dpo-group'),
            'type'    => 'checkbox',
            'tooltip' => '<h6>' . __('Posts', 'gravity-forms-dpo-group') . '</h6>' . __(
                    'Enable this option if you would like to only create the post after payment has been received.',
                    'gravity-forms-dpo-group'
                ),
            'choices' => array(
                array(
                    'label' => __('Create post only when payment is received.', 'gravity-forms-dpo-group'),
                    'name'  => 'delayPost',
                ),
            ),
        );
    }

    /**
     * @return array[]
     */
    public function getDpoConfigInstructions(): array
    {
        $description = '
            <p style="text-align: left;">' .
                       __(
                           'You will need a DPO Pay account in order to use the DPO Pay Add-On.',
                           'gravity-forms-dpo-group'
                       ) .
                       '</p>
            <ul>
                <li>' . sprintf(
                           __(
                               'Go to the %sDPO Pay Website%s in order to register an account.',
                               'gravity-forms-dpo-group'
                           ),
                           '<a href="https://dpogroup.com" target="_blank">',
                           '</a>'
                       ) . '</li>' .
                       '<li>' . __(
                           'Check \'I understand\' and click on \'Update Settings\' in order to proceed.',
                           'gravity-forms-dpo-group'
                       ) . '</li>' .
                       '</ul>
                <br/>';

        return array(
            array(
                'title'       => '',
                'description' => $description,
                'fields'      => array(
                    array(
                        'name'    => 'gf_dpo_group_configured',
                        'label'   => __('I understand', 'gravity-forms-dpo-group'),
                        'type'    => 'checkbox',
                        'choices' => array(
                            array(
                                'label' => __('', 'gravity-forms-dpo-group'),
                                'name'  => 'gf_dpo_group_configured',
                            ),
                        ),
                    ),
                    array(
                        'type'     => 'save',
                        'messages' => array(
                            'success' => __('Settings have been updated.', 'gravity-forms-dpo-group'),
                        ),
                    ),
                ),
            ),
        );
    }

    public function getBillingMsg (): array
    {
        return array(
            'name'  => 'message',
            'label' => __('DPO Pay does not currently support subscription billing', 'gravityformsstripe'),
            'style' => 'width:40px;text-align:center;',
            'type'  => 'checkbox',
        );
    }
}
