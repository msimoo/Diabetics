<?php
/**
 * Clinic PDF Generator — Print-optimized HTML report generator for Arabic/RTL reports
 * Outputs an HTML page with print CSS so the browser renders it beautifully.
 * User clicks "طباعة / حفظ PDF" to save as PDF via the browser's native print-to-PDF.
 * Uses only built-in PHP extensions (mbstring, GD, DOM, zlib)
 */
class ClinicPDF {
    private $margin = 20;
    private $content = '';
    private $pageNum = 0;
    
    public function __construct($title = '') {
        $this->content = '';
        $this->pageNum = 0;
    }
    
    public function addPage() {
        $this->pageNum++;
        if ($this->pageNum > 1) {
            $this->content .= "\n---PAGEBREAK---\n";
        }
    }
    
    public function setFont($size = 10, $bold = false) {
        // Font family/size handled by CSS
    }
    
    public function write($text, $size = 10, $bold = false, $align = 'right') {
        $style = $bold ? 'font-weight:bold;' : '';
        $this->content .= "<div style='font-size:{$size}pt;{$style}text-align:{$align};margin-bottom:4px;'>" 
                         . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . "</div>\n";
    }
    
    public function writeHTML($html) {
        $this->content .= $html . "\n";
    }
    
    /**
     * Render an HTML table. Content cells are NOT escaped to allow inline HTML (badges, spans).
     * Only pass trusted/safe HTML in cell values.
     */
    public function table($headers, $rows) {
        $this->content .= "<table style='width:100%;border-collapse:collapse;margin:8px 0;font-size:9pt;'>\n";
        $this->content .= "<thead><tr>";
        foreach ($headers as $h) {
            $this->content .= "<th style='background:#0a7e6e;color:#fff;padding:6px 8px;border:1px solid #ddd;text-align:right;font-weight:bold;'>" 
                            . htmlspecialchars($h, ENT_QUOTES, 'UTF-8') . "</th>";
        }
        $this->content .= "</tr></thead><tbody>\n";
        foreach ($rows as $row) {
            $this->content .= "<tr>";
            foreach ($row as $cell) {
                $this->content .= "<td style='padding:4px 8px;border:1px solid #ddd;text-align:right;'>" 
                                . $cell . "</td>"; // NOT escaped — allows badges, spans
            }
            $this->content .= "</tr>\n";
        }
        $this->content .= "</tbody></table>\n";
    }
    
    public function line() {
        $this->content .= "<hr style='border:none;border-top:1px solid #ccc;margin:8px 0;'>\n";
    }
    
    public function spacer($h = 10) {
        $this->content .= "<div style='height:{$h}px;'></div>\n";
    }
    
    public function kpiCard($label, $value, $color = '#0a7e6e') {
        $this->content .= "<div style='display:inline-block;width:30%;margin:4px 1%;padding:8px 12px;border-radius:6px;border:1px solid #e2e8f0;text-align:center;'>"
                        . "<div style='font-size:16pt;font-weight:bold;color:{$color};'>{$value}</div>"
                        . "<div style='font-size:8pt;color:#64748b;'>{$label}</div></div>\n";
    }
    
    /**
     * Render the full HTML page with print CSS and output it.
     * Calls exit() after output so only the report is rendered.
     */
    public function render($filename = 'report.pdf') {
        $body = $this->buildHTML();
        
        $fullHTML = "<!DOCTYPE html><html dir='rtl' lang='ar'><head><meta charset='utf-8'>"
                  . "<title>{$filename}</title>"
                  . "<style>
                        @page { size: A4; margin: 15mm; }
                        body { font-family: 'Tajawal', 'Traditional Arabic', Arial, sans-serif; 
                               font-size: 10pt; line-height: 1.6; color: #1e293b; 
                               direction: rtl; padding: 0; margin: 0; }
                        .page-break { page-break-before: always; }
                        table { width: 100%; border-collapse: collapse; margin: 8px 0; }
                        th, td { padding: 6px 8px; border: 1px solid #ddd; text-align: right; }
                        th { background: #0a7e6e; color: white; font-weight: bold; }
                        tr:nth-child(even) { background: #f8fafc; }
                        .header { text-align: center; padding: 20px 0; border-bottom: 2px solid #0a7e6e; margin-bottom: 20px; }
                        .header h1 { color: #0a7e6e; margin: 0; font-size: 18pt; }
                        .header p { color: #64748b; margin: 4px 0 0 0; font-size: 10pt; }
                        .footer { text-align: center; padding: 10px 0; border-top: 1px solid #e2e8f0; margin-top: 20px; font-size: 8pt; color: #94a3b8; }
                        .kpi-grid { text-align: center; margin: 10px 0; }
                        .kpi-item { display: inline-block; width: 30%; margin: 4px 1%; padding: 8px; border-radius: 6px; border: 1px solid #e2e8f0; text-align: center; }
                        .kpi-value { font-size: 16pt; font-weight: bold; }
                        .kpi-label { font-size: 8pt; color: #64748b; }
                        .section-title { font-size: 14pt; font-weight: bold; color: #0a7e6e; margin: 15px 0 8px 0; padding-bottom: 4px; border-bottom: 2px solid #0a7e6e; }
                        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 8pt; font-weight: bold; }
                        .badge-success { background: #d1fae5; color: #059669; }
                        .badge-danger { background: #fee2e2; color: #dc2626; }
                        .badge-warning { background: #fef3c7; color: #d97706; }
                        .risk-meter { height: 6px; border-radius: 3px; background: #e2e8f0; margin: 4px 0; overflow: hidden; }
                        .risk-fill { height: 100%; border-radius: 3px; }
                        @media print {
                            .no-print { display: none !important; }
                            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                        }
                    </style></head><body>
                    <div class='no-print' style='text-align:center;padding:10px;background:#f8fafc;border-bottom:1px solid #e2e8f0;margin-bottom:20px;'>
                        <button onclick='window.print()' style='padding:8px 20px;background:#0a7e6e;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:12px;'>🖨️ طباعة / حفظ PDF</button>
                        <button onclick='window.close()' style='padding:8px 20px;background:#e2e8f0;color:#1e293b;border:none;border-radius:6px;cursor:pointer;font-size:12px;margin-right:8px;'>❌ إغلاق</button>
                    </div>
                    {$body}
                    <div class='footer'>تم الإنشاء بواسطة نظام عيادة السكري — Sari Clinic System | " . date('Y-m-d H:i') . "</div>
                    </body></html>";
        
        header('Content-Type: text/html; charset=utf-8');
        echo $fullHTML;
        exit; // Only the report page is shown
    }
    
    private function buildHTML() {
        $pages = explode("\n---PAGEBREAK---\n", $this->content);
        $html = '';
        foreach ($pages as $i => $page) {
            if ($i > 0) $html .= "<div class='page-break'></div>\n";
            $html .= $page;
        }
        return $html;
    }
}
?>