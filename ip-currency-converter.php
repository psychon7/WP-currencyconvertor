<?php
/**
 * Plugin Name: IP Currency Converter
 * Plugin URI: https://www.soundcube.shop/ip-currency-converter
 * Description: This is a currency converter plugin based on visitor's IP.
 * Version: 1.0
 * Author: Your Mohan
 * Author URI: https://www.soundcube.shop
 **/


// Get the currency code based on visitor's IP address
function ip_currency_converter_get_currency() {
    $ip = $_SERVER['REMOTE_ADDR'];
    $response = wp_remote_get("https://ipapi.co/{$ip}/json/");
    
    if (is_wp_error($response)) {
        // If there's an error, return default currency
        return 'USD';
    }
    
    $data = json_decode(wp_remote_retrieve_body($response));
    return $data->currency ?? 'USD';
}

// Get the conversion rate from USD to the given currency
function ip_currency_converter_get_conversion_rate($currency) {
    // Get conversion rate from transient, if available
    $transient_name = 'ip_currency_converter_rate_' . $currency;
    $conversion_rate = get_transient($transient_name);
    
    if ($conversion_rate === false) {
        // If transient is not set, make API request
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => "https://currency-converter27.p.rapidapi.com/currency/convert",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode([
                'from' => 'USD',
                'to' => $currency,
                'amount' => 1
            ]),
            CURLOPT_HTTPHEADER => [
                "X-RapidAPI-Host: currency-converter27.p.rapidapi.com",
                "X-RapidAPI-Key: 4e1eae0c1amshe32171c489dc2ffp11a467jsn25b1837841f4",
                "content-type: application/json"
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        
        curl_close($curl);

        if ($err) {
            // If there's an error, return 1 to avoid changing the price
            return 1;
        } else {
            // Decode the response and get the conversion rate
            $response_data = json_decode($response, true);
            $conversion_rate = $response_data['rate'];

            // Store the conversion rate in a transient for 24 hours
            set_transient($transient_name, $conversion_rate, DAY_IN_SECONDS);
        }
    }
    
    return $conversion_rate;
}


// Change the displayed price based on the visitor's currency
function ip_currency_converter_change_price_display($price, $product) {
    $currency = ip_currency_converter_get_currency();
    $conversion_rate = ip_currency_converter_get_conversion_rate($currency);
    
    // Convert sale price
    $converted_sale_price = $product->get_sale_price() * $conversion_rate;
    $converted_sale_price_html = wc_price($converted_sale_price, ['currency' => $currency]);

    // Display regular and sale price in USD
    $regular_price_html = wc_price($product->get_regular_price(), ['currency' => 'USD']);
    $sale_price_html = wc_price($product->get_sale_price(), ['currency' => 'USD']);

    if ($product->is_on_sale()) {
        // If the product is on sale, show the regular price striked, sale price in USD, and converted sale price in local currency
        $price = '<del aria-hidden="true">' . $regular_price_html . '</del> ' . $sale_price_html;
        $price .= ' (' . $converted_sale_price_html . ' this is a reference price, conversion rates may vary)';
    } else {
        // If the product is not on sale, just return the regular price
        $price = $regular_price_html;
    }
    
    return $price;
}

add_filter('woocommerce_get_price_html', 'ip_currency_converter_change_price_display', 10, 2);

