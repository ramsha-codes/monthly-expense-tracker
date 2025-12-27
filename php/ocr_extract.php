<?php
/**
 * Mac-Optimized OCR Extraction
 */

// --- BOOTSTRAP: ERROR CATCHING ---
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_CORE_ERROR)) {
        if (!headers_sent()) {
            http_response_code(200); 
            header('Content-Type: application/json');
        }
        echo json_encode(["error" => "PHP Fatal Error", "details" => $error['message']]);
        exit;
    }
});

session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

try {
    require 'db.php';

    if (!isset($_SESSION['user_id'])) throw new Exception("Not logged in");
    if (!isset($_FILES['receipt']))   throw new Exception("No file uploaded");

    // --- 1. SET PATHS FOR MAC ---
    $currentPath = getenv('PATH');
    $macPaths = "/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin";
    putenv("PATH=$macPaths:$currentPath");

    // --- 2. FIND BINARIES ---
    function findBinary($name) {
        $path = trim(shell_exec("which $name 2>/dev/null") ?? ''); // Fixed deprecated trim
        if ($path) return $path;
        
        $candidates = [
            "/opt/homebrew/bin/$name",
            "/usr/local/bin/$name",
            "/usr/bin/$name",
            "/bin/$name"
        ];
        foreach ($candidates as $c) {
            if (file_exists($c)) return $c;
        }
        return false;
    }

    $tesseractCmd = findBinary('tesseract');
    if (!$tesseractCmd) {
        throw new Exception("Tesseract not found. Run 'brew install tesseract' in Terminal.");
    }

    // --- 3. HANDLE UPLOAD ---
    $file = $_FILES['receipt'];
    if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception("Upload Error: " . $file['error']);

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $validExts = ['pdf', 'jpg', 'jpeg', 'png'];
    if (!in_array($ext, $validExts)) {
        throw new Exception("Invalid file type. Only PDF, JPG, PNG allowed.");
    }

    if ($ext === 'pdf') {
        $gsCmd = findBinary('gs');
        if (!$gsCmd) {
            throw new Exception("Ghostscript (gs) not found. Required for PDF OCR. Run 'brew install ghostscript'.");
        }
    }

    // --- 4. USE LOCAL TEMP DIRECTORY (FIX PERMISSION ISSUE) ---
    // Instead of sys_get_temp_dir() which restricts 'daemon' user
    $uploadDir = __DIR__ . '/temp';
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            throw new Exception("Failed to create temp directory. Check permissions for " . __DIR__);
        }
    }
    
    $uniqueId = uniqid('ocr_', true);
    $inputPath = $uploadDir . DIRECTORY_SEPARATOR . $uniqueId . '.' . $ext;
    $outputBase = $uploadDir . DIRECTORY_SEPARATOR . $uniqueId . '_out';

    if (!move_uploaded_file($file['tmp_name'], $inputPath)) {
        throw new Exception("Failed to save uploaded file to $uploadDir.");
    }

    // --- 5. EXECUTE OCR ---
    $cmd = sprintf(
        '%s %s %s 2>&1',
        escapeshellcmd($tesseractCmd),
        escapeshellarg($inputPath),
        escapeshellarg($outputBase)
    );

    $output = [];
    $returnVar = 0;
    exec($cmd, $output, $returnVar);

    // Cleanup input
    @unlink($inputPath);

    // --- 6. RESULT ---
    if ($returnVar !== 0) {
        $err = implode("\n", $output);
        throw new Exception("Tesseract Error (Code $returnVar): " . $err);
    }

    $outputTxt = $outputBase . ".txt";
    if (file_exists($outputTxt)) {
        $text = file_get_contents($outputTxt);
        @unlink($outputTxt);
        
        // --- 7. DETECT AMOUNT (Logic: Find largest X.XX number) ---
        $detectedAmount = null;
        // Regex looks for numbers with 2 decimal places (e.g., 10.99, 1,200.00)
        if (preg_match_all('/(\d{1,3}(?:,\d{3})*\.\d{2})/', $text, $matches)) {
            $values = [];
            foreach ($matches[1] as $raw) {
                // Remove commas to get pure float value
                $val = (float)str_replace(',', '', $raw);
                $values[] = $val;
            }
            // Assume the largest amount on the receipt is the Total
            if (!empty($values)) {
                $detectedAmount = max($values);
            }
        }

        echo json_encode([
            "status" => "success", 
            "text" => trim($text),
            "amount" => $detectedAmount
        ]);
    } else {
        throw new Exception("OCR finished but no output text found.");
    }

} catch (Exception $e) {
    http_response_code(200);
    echo json_encode(["error" => "OCR Failed", "details" => $e->getMessage()]);
}
?>