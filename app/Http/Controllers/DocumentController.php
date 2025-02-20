<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LetterHead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver; // For GD
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver; // For Imagick (if installed)
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use App\Helper;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Shared\Converter;
// use PhpOffice\PhpWord\Writer\MPDF;
use PhpOffice\PhpWord\Writer\TCPDF;
use PhpOffice\PhpWord\Writer\DomPDF;
use Mpdf\Mpdf;
use PhpOffice\PhpWord\TemplateProcessor;
use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
class DocumentController extends Controller
{
    public function uploadLetterhead(Request $request)
    {
        try{
        $file = $request->file('letterhead');
        $userId = $request->input('userId');
        $propertyId = $request->input('propertyId');
        $fileName = uniqid() . '.' . $file->getClientOriginalExtension();
        $file->move(public_path('uploads/letterheads'), $fileName);
        $letterheadexists = Letterhead::where('user_id', $userId)
            ->where('property_id', $propertyId)
            ->first();

        if ($letterheadexists) {
            Letterhead::where('user_id', $userId)
                ->where('property_id', $propertyId)->update([
                        'file_path' => 'letterheads/' . $fileName,
                    ]);
        } else {
         Letterhead::create([
                'user_id' => $userId,
                'property_id' => $propertyId,
                'file_path' => 'letterheads/' . $fileName,
            ]);
        }

        return response()->json([
            'status' => 'success',
        ]);
     } catch (\Exception $e) {
            // Log the error
            $errorFrom = 'uploadLetterhead';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while saving the data',
            ], 400);
        }
    }

    public function generatePaymentPdf1(Request $request)
    {
        // Load uploaded letterhead
        $letterhead = Letterhead::findOrFail($request->letterhead_id);
        $letterheadPath = public_path('uploads/' . $letterhead->file_path);
        
        if (!file_exists($letterheadPath)) {
            return response()->json(['error' => 'Letterhead not found.'], 404);
        }
    
        // Load the DOCX file
        $templateProcessor = new TemplateProcessor($letterheadPath);
    
        // Add content dynamically (between header & footer)
        $dynamicContent = "Dear User,\n\nThis is dynamically added content.\n\nBest Regards,\nYour Company";

        $templateProcessor->setValue('content', $dynamicContent);
    
        // Save the modified document
        // $newFileName = 'generated_letterhead_' . time() . '.docx';
        $newFilePath = public_path('uploads/letterheads/generated_doc.docx');
        $templateProcessor->saveAs($newFilePath);
    
        return response()->download($newFilePath);
    }

    
    public function generatePaymentPdf(Request $request)
    {
        // Find the letterhead file
        $letterhead = Letterhead::findOrFail($request->letterhead_id);
        $letterheadPath = public_path('uploads/' . $letterhead->file_path);
    
        if (!file_exists($letterheadPath)) {
            return response()->json(['error' => 'Template file not found'], 404);
        }
    
        // Load the DOCX file while preserving header/footer
        $zip = new \ZipArchive();
        if ($zip->open($letterheadPath) === true) {
            $xmlContent = $zip->getFromName('word/document.xml'); // Get document body XML
            $zip->close();
        } else {
            return response()->json(['error' => 'Unable to open DOCX file'], 500);
        }
    
        // Insert new content dynamically
        $newContent = "<w:p><w:r><w:t>Invoice for: John Doe</w:t></w:r></w:p>";
        $newContent .= "<w:p><w:r><w:t>Invoice Date: " . now()->format('Y-m-d') . "</w:t></w:r></w:p>";
        $newContent .= "<w:p><w:r><w:t>Total Amount: $500.00</w:t></w:r></w:p>";
    
        // Insert the new content before the closing </w:body> tag
        $xmlContent = str_replace('</w:body>', $newContent . '</w:body>', $xmlContent);
    
        // Save the modified XML back into the DOCX file
        $newDocPath = public_path('uploads/'.$letterhead->id.'output.docx');
        copy($letterheadPath, $newDocPath); // Create a copy to modify
        if ($zip->open($newDocPath) === true) {
            $zip->deleteName('word/document.xml'); // Remove old content
            $zip->addFromString('word/'.$letterhead->id.'document.xml', $xmlContent); // Add new content
            $zip->close();
        } else {
            return response()->json(['error' => 'Unable to modify DOCX file'], 500);
        }
    
        return 'done';
    }
    
    
}
    
    
