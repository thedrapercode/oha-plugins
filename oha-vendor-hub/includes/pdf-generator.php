<?php
/**
 * HTML Agreement Generation for Vendor Applications
 * 
 * CRITICAL: "PDF" in this file means "HTML Agreement"
 * - Files are .html, not .pdf
 * - Browser "Print to PDF" is the intended workflow
 * - Legacy naming (_pdf_url, pdf-generator.php) retained for backwards compatibility
 * 
 * Agreement artifact = HTML file (legacy naming retained)
 */

if (!defined('ABSPATH')) exit;

/**
 * Generate HTML agreement for vendor application
 * 
 * Note: Function name says "pdf" but generates HTML (legacy naming)
 * 
 * @param int $app_id Vendor application ID
 * @return array|false Agreement info array or false on failure
 */
function oha_vhcpt_generate_vendor_pdf($app_id) {
    // HTML agreement generation (legacy naming retained for compatibility)
    // Switched to HTML-only in v2.4.0 - NO FPDF attempts
    return oha_vhcpt_generate_simple_pdf($app_id);
}

/**
 * Generate actual PDF using FPDF library
 */
function oha_vhcpt_generate_fpdf_pdf($app_id) {
    // Get application data
    $post = get_post($app_id);
    if (!$post || $post->post_type !== 'vendor_application') {
        error_log('OHA PDF: Invalid post type or post not found');
        return false;
    }
    
    // Include FPDF library
    $fpdf_path = OHA_VHCPT_PATH . 'includes/lib/fpdf.php';
    if (!file_exists($fpdf_path)) {
        error_log('OHA PDF: FPDF library not found, falling back to HTML');
        return oha_vhcpt_generate_simple_pdf($app_id);
    }
    
    require_once($fpdf_path);
    
    // Get metadata
    $company_name = get_post_meta($app_id, 'company_name', true);
    $email = get_post_meta($app_id, 'email', true);
    $phone = get_post_meta($app_id, 'phone', true);
    $first_name = get_post_meta($app_id, 'first_name', true);
    $last_name = get_post_meta($app_id, 'last_name', true);
    $website = get_post_meta($app_id, 'website', true);
    $product_type = get_post_meta($app_id, 'product_type', true);
    $booths = get_post_meta($app_id, 'booths', true) ?: 1;
    $signature_name = get_post_meta($app_id, '_signature_name', true);
    $signature_date = get_post_meta($app_id, '_signature_date', true);
    $signature_ip = get_post_meta($app_id, '_signature_ip', true);
    $signature_data = get_post_meta($app_id, '_signature_data', true);
    
    try {
        // Create PDF
        $pdf = new FPDF('P', 'mm', 'Letter');
        $pdf->AddPage();
        $pdf->SetAutoPageBreak(true, 18);

        // Helper: section header
        $section_header = function($title) use ($pdf) {
            // Dark red bar with white text
            $pdf->SetFillColor(139, 0, 0);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 8, $title, 0, 1, 'L', true);
            $pdf->Ln(2);
            // Reset
            $pdf->SetTextColor(0, 0, 0);
        };

        // Header block
        $pdf->SetFont('Arial', 'B', 18);
        $pdf->SetTextColor(139, 0, 0);
        $pdf->Cell(0, 9, 'Ohio Haunters Association', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 13);
        $pdf->SetTextColor(50, 50, 50);
        $pdf->Cell(0, 7, 'Convention 2026 - Vendor Agreement', 0, 1, 'C');
        $pdf->Ln(2);
        // Meta
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->Cell(0, 5, 'Application ID: ' . $app_id . ' | Generated: ' . current_time('F j, Y g:i a'), 0, 1, 'C');
        $pdf->Ln(4);
        $pdf->SetTextColor(0, 0, 0);
        
        // Company Information
        $section_header('Company Information');
        $pdf->SetFont('Arial', '', 10);
        $labelW = 45;
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($labelW, 6, 'Company Name:', 0, 0);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, $company_name, 0, 1);

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($labelW, 6, 'Primary Contact:', 0, 0);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, trim($first_name . ' ' . $last_name), 0, 1);

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($labelW, 6, 'Email:', 0, 0);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, $email, 0, 1);

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($labelW, 6, 'Phone:', 0, 0);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, $phone, 0, 1);

        if (!empty($website)) {
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell($labelW, 6, 'Website:', 0, 0);
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(0, 6, $website, 0, 1);
        }

        if (!empty($product_type)) {
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell($labelW, 6, 'Products:', 0, 0);
            $pdf->SetFont('Arial', '', 10);
            $pdf->MultiCell(0, 6, $product_type);
        }

        $pdf->Ln(2);
        
        // Booth Reservation
        $section_header('Booth Reservation');
        $total_amount = intval($booths) * 250;
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($labelW, 6, 'Number of Booths:', 0, 0);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, $booths, 0, 1);

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($labelW, 6, 'Price per Booth:', 0, 0);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, '$250.00', 0, 1);

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($labelW, 6, 'Total Amount:', 0, 0);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 6, '$' . number_format($total_amount, 2), 0, 1);
        $pdf->Ln(2);
        
        // Venue Details
        $section_header('Venue Details');
        $pdf->SetFont('Arial', '', 10);
        $pdf->MultiCell(0, 6,
            "Venue: Mansfield Reformatory, Mansfield, Ohio\n" .
            "Event Dates: May 9-10, 2026\n" .
            "Load-in: Friday, May 8, 2026 (4:00 PM - 9:00 PM)\n" .
            "Load-out: Sunday, May 10, 2026 (by 6:00 PM)");
        $pdf->Ln(1);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 6, 'Each booth includes:', 0, 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->MultiCell(0, 6,
            "- 10' x 10' space\n" .
            "- 2 chairs\n" .
            "- Access for up to 4 people\n" .
            "- 2 ball passes\n" .
            "- Tables available upon request");
        $pdf->Ln(2);

        // Terms & Conditions
        $section_header('Terms & Conditions');
        $pdf->SetFont('Arial', '', 10);
        $pdf->MultiCell(0, 6,
            "- Venue is not temperature controlled and may have animals present\n" .
            "- NO screws, nails, or tape may be used on walls or floors\n" .
            "- Nothing may be attached to sprinkler systems\n" .
            "- No outside food or beverages allowed\n" .
            "- All payments are NON-REFUNDABLE\n" .
            "- Vendors are responsible for their own setup and breakdown\n" .
            "- Vendors must comply with all venue rules and regulations\n" .
            "- OHA reserves the right to refuse service to anyone");
        $pdf->Ln(4);
        
        // Signature section (with image if available)
        $section_header('Electronic Signature');
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($labelW, 6, 'Printed Name:', 0, 0);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, $signature_name, 0, 1);

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($labelW, 6, 'Date Signed:', 0, 0);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, $signature_date, 0, 1);

        if (!empty($signature_ip)) {
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell($labelW, 6, 'IP Address:', 0, 0);
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(0, 6, $signature_ip, 0, 1);
        }

        // Signature image: stored as data URL or base64
        $tmp_sig = '';
        if (!empty($signature_data) && is_string($signature_data)) {
            $b64 = $signature_data;
            if (str_starts_with($b64, 'data:image')) {
                $parts = explode(',', $b64, 2);
                if (count($parts) === 2) {
                    $b64 = $parts[1];
                }
            }
            $raw = base64_decode($b64);
            if ($raw !== false) {
                $tmp_sig = wp_tempnam('oha-signature-' . $app_id . '.png');
                if ($tmp_sig) {
                    file_put_contents($tmp_sig, $raw);
                }
            }
        }
        if (!empty($tmp_sig) && file_exists($tmp_sig)) {
            $pdf->Ln(2);
            // Fit nicely within page
            $x = $pdf->GetX();
            $y = $pdf->GetY();
            $pdf->Image($tmp_sig, $x, $y, 90, 0, 'PNG');
            $pdf->Ln(30);
            @unlink($tmp_sig);
        }

        // Footer note
        $pdf->Ln(6);
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->SetTextColor(110, 110, 110);
        $pdf->MultiCell(0, 4,
            "This document constitutes a legally binding agreement between the vendor and Ohio Haunters Association.\n" .
            "By signing electronically, you acknowledge that you have read, understood, and agree to all terms and conditions.\n" .
            "Electronic signatures are legally binding under the ESIGN Act (2000) and UETA.", 0, 'C');
        $pdf->SetTextColor(0, 0, 0);
        
        // Save PDF
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/vendor-agreements';
        
        if (!file_exists($pdf_dir)) {
            wp_mkdir_p($pdf_dir);
        }
        
        $filename = 'vendor-agreement-' . $app_id . '-' . time() . '.pdf';
        $filepath = $pdf_dir . '/' . $filename;
        $pdf->Output('F', $filepath);
        
        // Save URL to meta
        $pdf_url = $upload_dir['baseurl'] . '/vendor-agreements/' . $filename;
        update_post_meta($app_id, '_agreement_url', $pdf_url);
        update_post_meta($app_id, '_agreement_file', $filepath);
        
        error_log('OHA PDF: Generated real PDF at ' . $filepath);
        return ['path' => $filepath, 'url' => $pdf_url];
        
    } catch (Exception $e) {
        error_log('OHA PDF: FPDF generation failed: ' . $e->getMessage());
        return oha_vhcpt_generate_simple_pdf($app_id);
    }
}

/**
 * Generate HTML agreement (legacy name: "simple PDF")
 * 
 * Creates a styled HTML file that can be saved as PDF via browser Print function.
 * This is NOT a PDF file - it's an HTML file designed for browser-based PDF conversion.
 * 
 * @param int $app_id Vendor application ID
 * @return array|false Array with 'path', 'url', 'filename' or false on failure
 */
function oha_vhcpt_generate_simple_pdf($app_id) {
    // Get application data
    $post = get_post($app_id);
    if (!$post || $post->post_type !== 'vendor_application') {
        return false;
    }
    
    // Get all metadata
    $company_name = get_post_meta($app_id, 'company_name', true);
    $email = get_post_meta($app_id, 'email', true);
    $phone = get_post_meta($app_id, 'phone', true);
    $website = get_post_meta($app_id, 'website', true);
    $first_name = get_post_meta($app_id, 'first_name', true);
    $last_name = get_post_meta($app_id, 'last_name', true);
    $product_type = get_post_meta($app_id, 'product_type', true);
    $booths = get_post_meta($app_id, 'booths', true) ?: 1;
    $status = get_post_meta($app_id, '_oha_status', true);
    
    // Signature data
    $signature_data = get_post_meta($app_id, '_signature_data', true);
    $signature_name = get_post_meta($app_id, '_signature_name', true);
    $signature_date = get_post_meta($app_id, '_signature_date', true);
    $signature_ip = get_post_meta($app_id, '_signature_ip', true);
    
    // Payment info - SAFE parsing for all formats (from handover doc fix)
    $payment_date = '';
    $history = get_post_meta($app_id, '_oha_history', true);
    
    // Handle serialized, array, or JSON history safely
    if (is_string($history)) {
        $maybe = maybe_unserialize($history);
        if ($maybe !== false || $history === 'b:0;') {
            $history = $maybe;
        }
    }
    
    $hist_array = [];
    
    if (is_array($history)) {
        $hist_array = $history;
    } elseif (is_string($history) && $history !== '') {
        $decoded = json_decode($history, true);
        if (is_array($decoded)) {
            $hist_array = $decoded;
        }
    }
    
    if (!empty($hist_array)) {
        foreach ($hist_array as $entry) {
            if (
                isset($entry['type']) &&
                ($entry['type'] === 'payment_completed' || $entry['type'] === 'final_application_signed')
            ) {
                $payment_date = $entry['ts'] ?? '';
                break;
            }
        }
    }
    
    // Fallback to signature date if no payment date found
    if (empty($payment_date) && !empty($signature_date)) {
        $payment_date = $signature_date;
    }
    
    // Normalize status for comparison (handles PAID, Paid, paid)
    $status_norm = strtolower(trim((string)$status));
    
    // Generate HTML content
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Vendor Agreement - ' . esc_html($company_name) . '</title>
    <style>
        @media print {
            @page { margin: 0.5in; }
            body { margin: 0; }
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #000;
            max-width: 8in;
            margin: 0 auto;
            padding: 20px;
            background: #fff;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #8b0000;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24pt;
            color: #8b0000;
        }
        .header h2 {
            margin: 5px 0 0 0;
            font-size: 16pt;
            color: #333;
            font-weight: normal;
        }
        .meta-info {
            text-align: center;
            font-size: 9pt;
            color: #666;
            margin-bottom: 25px;
        }
        .section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .section-title {
            background: #8b0000;
            color: #fff;
            padding: 8px 12px;
            font-size: 13pt;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .field-row {
            margin-bottom: 8px;
        }
        .field-label {
            display: inline-block;
            width: 140px;
            font-weight: bold;
            color: #333;
        }
        .field-value {
            display: inline;
            color: #000;
        }
        .signature-box {
            border: 2px solid #22c55e;
            padding: 15px;
            margin: 15px 0;
            background: #f0fdf4;
            page-break-inside: avoid;
        }
        .signature-img {
            max-width: 400px;
            max-height: 150px;
            border: 1px solid #d1d5db;
            background: #fff;
            padding: 10px;
            margin: 10px 0;
        }
        .payment-box {
            border: 2px solid #22c55e;
            padding: 15px;
            margin: 15px 0;
            background: #ecfdf5;
            page-break-inside: avoid;
        }
        .terms-list {
            font-size: 10pt;
            line-height: 1.6;
            margin-left: 20px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            font-size: 8pt;
            color: #666;
            text-align: center;
            font-style: italic;
        }
        .status-badge {
            display: inline-block;
            background: #22c55e;
            color: #fff;
            padding: 4px 12px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 10pt;
        }
    </style>
</head>
<body>';
    
    // Header
    $html .= '
    <div class="header">
        <h1>🎃 Ohio Haunters Association</h1>
        <h2>Convention 2026 - Vendor Agreement</h2>
    </div>
    
    <div class="meta-info">
        Application ID: ' . esc_html($app_id) . ' | Generated: ' . esc_html(current_time('F j, Y g:i a')) . '
    </div>';
    
    // Company Information
    $html .= '
    <div class="section">
        <div class="section-title">Company Information</div>
        <div class="field-row"><span class="field-label">Company Name:</span> <span class="field-value">' . esc_html($company_name) . '</span></div>
        <div class="field-row"><span class="field-label">Primary Contact:</span> <span class="field-value">' . esc_html($first_name . ' ' . $last_name) . '</span></div>
        <div class="field-row"><span class="field-label">Email:</span> <span class="field-value">' . esc_html($email) . '</span></div>
        <div class="field-row"><span class="field-label">Phone:</span> <span class="field-value">' . esc_html($phone) . '</span></div>';
    
    if (!empty($website)) {
        $html .= '<div class="field-row"><span class="field-label">Website:</span> <span class="field-value">' . esc_html($website) . '</span></div>';
    }
    
    $html .= '<div class="field-row"><span class="field-label">Products:</span> <span class="field-value">' . esc_html($product_type) . '</span></div>
    </div>';
    
    // Booth Information
    $total_amount = $booths * 250;
    $html .= '
    <div class="section">
        <div class="section-title">Booth Reservation</div>
        <div class="field-row"><span class="field-label">Number of Booths:</span> <span class="field-value">' . esc_html($booths) . '</span></div>
        <div class="field-row"><span class="field-label">Price per Booth:</span> <span class="field-value">$250.00</span></div>
        <div class="field-row"><span class="field-label">Total Amount:</span> <span class="field-value" style="font-size:14pt;font-weight:bold;">$' . number_format($total_amount, 2) . '</span></div>
    </div>';
    
    // Venue Details
    $html .= '
    <div class="section">
        <div class="section-title">Venue Details</div>
        <p style="margin:5px 0;"><strong>Venue:</strong> Mansfield Reformatory, Mansfield, Ohio</p>
        <p style="margin:5px 0;"><strong>Event Dates:</strong> May 9-10, 2026</p>
        <p style="margin:5px 0;"><strong>Load-in:</strong> Friday, May 8, 2026 (4:00 PM - 9:00 PM)</p>
        <p style="margin:5px 0;"><strong>Load-out:</strong> Sunday, May 10, 2026 (by 6:00 PM)</p>
        <p style="margin-top:10px;"><strong>Each booth includes:</strong></p>
        <ul class="terms-list">
            <li>8\' x 10\' space</li>
            <li>2 chairs</li>
            <li>Access for up to 4 people</li>
            <li>2 ball passes</li>
            <li>Tables available upon request</li>
        </ul>
    </div>';
    
    // Terms & Conditions
    $html .= '
    <div class="section">
        <div class="section-title">Terms & Conditions</div>
        <ul class="terms-list">
            <li>Venue is not temperature controlled and may have animals present</li>
            <li>NO screws, nails, or tape may be used on walls or floors</li>
            <li>Nothing may be attached to sprinkler systems</li>
            <li>No outside food or beverages allowed</li>
            <li>All payments are NON-REFUNDABLE</li>
            <li>Vendors are responsible for their own setup and breakdown</li>
            <li>Vendors must comply with all venue rules and regulations</li>
            <li>OHA reserves the right to refuse service to anyone</li>
        </ul>
    </div>';
    
    // Electronic Signature
    $html .= '
    <div class="section">
        <div class="signature-box">
            <div class="section-title" style="margin-bottom:15px;">Electronic Signature</div>';
    
    if (!empty($signature_data) && strpos($signature_data, 'data:image') === 0) {
        $html .= '<p style="margin:5px 0;font-weight:bold;">Signature:</p>
            <img src="' . esc_attr($signature_data) . '" class="signature-img" alt="Signature">';
    }
    
    $html .= '
            <div class="field-row"><span class="field-label">Printed Name:</span> <span class="field-value" style="font-weight:bold;">' . esc_html($signature_name) . '</span></div>
            <div class="field-row"><span class="field-label">Date Signed:</span> <span class="field-value">' . esc_html(!empty($signature_date) ? date('F j, Y g:i a', strtotime($signature_date)) : 'N/A') . '</span></div>';
    
    if (!empty($signature_ip)) {
        $html .= '<div class="field-row"><span class="field-label">IP Address:</span> <span class="field-value">' . esc_html($signature_ip) . '</span></div>';
    }
    
    $html .= '
        </div>
    </div>';
    
    // Payment Information - use normalized status (handles PAID, Paid, paid)
    if (!empty($payment_date) || $status_norm === 'paid') {
        $html .= '
        <div class="section">
            <div class="payment-box">
                <div class="section-title" style="margin-bottom:15px;">Payment Information</div>
                <div class="field-row"><span class="field-label">Payment Status:</span> <span class="status-badge">PAID</span></div>
                <div class="field-row"><span class="field-label">Payment Date:</span> <span class="field-value">' . esc_html(!empty($payment_date) ? date('F j, Y g:i a', strtotime($payment_date)) : current_time('F j, Y g:i a')) . '</span></div>
                <div class="field-row"><span class="field-label">Amount Paid:</span> <span class="field-value" style="font-weight:bold;font-size:12pt;">$' . number_format($total_amount, 2) . '</span></div>
            </div>
        </div>';
    }
    
    // Footer
    $html .= '
    <div class="footer">
        This document constitutes a legally binding agreement between the vendor and Ohio Haunters Association.<br>
        By signing electronically, you acknowledge that you have read, understood, and agree to all terms and conditions.<br>
        Electronic signatures are legally binding under the ESIGN Act (2000) and UETA.
    </div>
    
</body>
</html>';
    
    // Save to uploads directory
    $upload_dir = wp_upload_dir();
    $pdf_dir = $upload_dir['basedir'] . '/vendor-agreements';
    
    // Create directory if it doesn't exist
    if (!file_exists($pdf_dir)) {
        wp_mkdir_p($pdf_dir);
    }
    
    $filename = 'OHA-Vendor-Agreement-' . $app_id . '-' . sanitize_title($company_name) . '.html';
    $file_path = $pdf_dir . '/' . $filename;
    $file_url = $upload_dir['baseurl'] . '/vendor-agreements/' . $filename;
    
    // Save HTML file (agreement artifact)
    file_put_contents($file_path, $html);
    
    // Store file info in post meta
    // Always write BOTH keys to avoid Board Hub vs Vendor Hub conflicts:
    update_post_meta($app_id, '_agreement_url', $file_url);      // Primary key
    update_post_meta($app_id, '_pdf_url', $file_url);            // Legacy alias (for compatibility)
    update_post_meta($app_id, '_agreement_file', $file_path);    // File path
    update_post_meta($app_id, '_pdf_path', $file_path);          // Legacy alias
    
    // Truth tracking: when was agreement actually generated
    update_post_meta($app_id, '_agreement_generated_at', current_time('mysql'));
    update_post_meta($app_id, '_pdf_generated', current_time('mysql')); // Legacy alias
    
    return [
        'path' => $file_path,
        'url' => $file_url,
        'filename' => $filename
    ];
}

/**
 * Email vendor with agreement download link (no attachment)
 * v2.3.3 - Simplified approach for reliability
 */
function oha_vhcpt_email_vendor_pdf($app_id) {
    error_log('=== OHA AGREEMENT EMAIL START ===');
    error_log('App ID: ' . $app_id);
    
    // Generate the agreement (creates HTML file)
    $pdf_info = oha_vhcpt_generate_vendor_pdf($app_id);

    // Normalize legacy return value
    if (is_string($pdf_info) && !empty($pdf_info)) {
        $pdf_info = [
            'path' => $pdf_info,
            'url'  => get_post_meta($app_id, '_agreement_url', true),
        ];
    }
    
    if (!$pdf_info) {
        error_log('OHA EMAIL: Agreement generation failed');
        return false;
    }
    
    // Get agreement URL - check multiple meta keys for backwards compatibility
    $agreement_url = '';
    if (!empty($pdf_info['url'])) {
        $agreement_url = $pdf_info['url'];
    } else {
        $agreement_url = get_post_meta($app_id, '_agreement_url', true);
        if (empty($agreement_url)) {
            $agreement_url = get_post_meta($app_id, '_pdf_url', true);
        }
    }
    
    if (empty($agreement_url)) {
        error_log('OHA EMAIL: No agreement URL found');
        return false;
    }
    
    error_log('Agreement URL: ' . $agreement_url);
    
    // Get vendor info
    $email = get_post_meta($app_id, 'email', true);
    if (empty($email)) {
        $email = get_post_meta($app_id, 'company_email', true);
    }
    
    if (!is_email($email)) {
        error_log('OHA EMAIL: Invalid email address: ' . $email);
        return false;
    }
    
    $company_name = get_post_meta($app_id, 'company_name', true);
    $first_name = get_post_meta($app_id, 'first_name', true);
    
    error_log('Sending to: ' . $email);
    
    // Build email
    $subject = 'Your OHA Convention 2026 Vendor Agreement';
    
    $body = "Hi " . $first_name . ",\n\n";
    $body .= "Thank you for completing your vendor registration for the Ohio Haunters Convention 2026!\n\n";
    $body .= "Your signed vendor agreement for " . $company_name . " is ready.\n\n";
    $body .= "📄 DOWNLOAD YOUR AGREEMENT:\n";
    $body .= $agreement_url . "\n\n";
    $body .= "You can view and save this document by opening it in your browser and using File > Print > Save as PDF.\n\n";
    $body .= "Event Details:\n";
    $body .= "• Venue: Mansfield Reformatory, Mansfield, Ohio\n";
    $body .= "• Dates: May 9-10, 2026\n";
    $body .= "• Load-in: Friday, May 8, 2026 (4:00 PM - 9:00 PM)\n\n";
    $body .= "Please keep this agreement for your records. You can also download it anytime from your vendor dashboard.\n\n";
    $body .= "We look forward to seeing you at the convention!\n\n";
    $body .= "If you have any questions, please reply to this email or contact us at webmaster@ohiohaunters.com\n\n";
    $body .= "Thanks,\n";
    $body .= "Ohio Haunters Association\n";
    $body .= "https://ohiohaunters.com";
    
    $headers = [
        'From: Ohio Haunters Association <noreply@ohiohaunters.com>',
        'Reply-To: webmaster@ohiohaunters.com'
    ];
    
    // NO ATTACHMENT - just send the link!
    error_log('Sending email with agreement link...');
    $sent = wp_mail($email, $subject, $body, $headers);
    
    error_log('wp_mail returned: ' . ($sent ? 'TRUE' : 'FALSE'));
    
    if ($sent) {
        // Truth tracking: when was email actually sent
        update_post_meta($app_id, '_agreement_emailed_at', current_time('mysql')); // Primary key
        update_post_meta($app_id, '_pdf_emailed', current_time('mysql'));          // Legacy alias
        update_post_meta($app_id, '_pdf_emailed_to', $email);                      // Email address
        error_log('OHA EMAIL: Success! Email sent with download link');
    } else {
        error_log('OHA EMAIL: FAILED - wp_mail returned false');
    }
    
    error_log('=== OHA AGREEMENT EMAIL END ===');
    
    return $sent;
}
