<?php

namespace App\Helpers;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Models\Sale;
use App\Models\Product;

class QrCodeHelper
{
    /**
     * Generate a QR code for a digital receipt URL
     * 
     * @param Sale $sale
     * @return string
     */
    public static function generateReceiptQrCode($sale)
    {
        $receiptUrl = route('pos.receipt', $sale->id);
        
        // Generate QR code with the receipt URL
        $qrCode = QrCode::size(200)
            ->format('svg')
            ->errorCorrection('M')
            ->generate($receiptUrl);
            
        return $qrCode;
    }
    
    /**
     * Generate a QR code for a product
     * 
     * @param Product $product
     * @return string
     */
    public static function generateProductQrCode($product)
    {
        $productUrl = route('pos.product', $product->id);
        
        // Generate QR code with the product URL
        $qrCode = QrCode::size(100)
            ->format('svg')
            ->errorCorrection('M')
            ->generate($productUrl);
            
        return $qrCode;
    }
    
    /**
     * Generate a vCard QR code for the store
     * 
     * @param array $storeInfo
     * @return string
     */
    public static function generateStoreVcard($storeInfo)
    {
        $vcard = "BEGIN:VCARD\n";
        $vcard .= "VERSION:3.0\n";
        $vcard .= "N:" . ($storeInfo['name'] ?? 'Store Name') . ";\n";
        $vcard .= "FN:" . ($storeInfo['name'] ?? 'Store Name') . "\n";
        
        if (isset($storeInfo['phone'])) {
            $vcard .= "TEL;TYPE=work,voice:" . $storeInfo['phone'] . "\n";
        }
        
        if (isset($storeInfo['email'])) {
            $vcard .= "EMAIL:" . $storeInfo['email'] . "\n";
        }
        
        if (isset($storeInfo['website'])) {
            $vcard .= "URL:" . $storeInfo['website'] . "\n";
        }
        
        if (isset($storeInfo['address'])) {
            $vcard .= "ADR;TYPE=work:;;" . $storeInfo['address'] . "\n";
        }
        
        $vcard .= "END:VCARD";
        
        // Generate QR code with the vCard data
        $qrCode = QrCode::size(200)
            ->format('svg')
            ->errorCorrection('M')
            ->generate($vcard);
            
        return $qrCode;
    }
    
    /**
     * Generate a payment QR code (e.g., for PayPal, Venmo, etc.)
     * 
     * @param array $paymentInfo
     * @return string
     */
    public static function generatePaymentQrCode($paymentInfo)
    {
        // Format varies by payment provider, this is a simplified example
        $paymentData = "PAY:";
        
        if (isset($paymentInfo['provider'])) {
            $paymentData .= $paymentInfo['provider'] . ";";
        }
        
        if (isset($paymentInfo['account'])) {
            $paymentData .= "ACC:" . $paymentInfo['account'] . ";";
        }
        
        if (isset($paymentInfo['amount'])) {
            $paymentData .= "AMT:" . $paymentInfo['amount'] . ";";
        }
        
        if (isset($paymentInfo['reference'])) {
            $paymentData .= "REF:" . $paymentInfo['reference'] . ";";
        }
        
        // Generate QR code with the payment data
        $qrCode = QrCode::size(200)
            ->format('svg')
            ->errorCorrection('M')
            ->generate($paymentData);
            
        return $qrCode;
    }
}
