<?php

namespace App\Traits;

use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

trait HandlesBulkFileUpload
{
    /**
     * Parse file (CSV or Excel) and return array of data rows
     */
    protected function parseFile($file): array
    {
        $fileExtension = strtolower($file->getClientOriginalExtension());
        
        if (in_array($fileExtension, ['xlsx', 'xls'])) {
            return $this->parseExcel($file);
        } else {
            return $this->parseCSV($file);
        }
    }

    /**
     * Parse CSV file
     */
    protected function parseCSV($file): array
    {
        $handle = fopen($file->getPathname(), 'r');
        
        if (!$handle) {
            return [
                'success' => false,
                'errors' => ['Could not open CSV file']
            ];
        }

        $headers = fgetcsv($handle);
        
        if (!$headers) {
            fclose($handle);
            return [
                'success' => false,
                'errors' => ['CSV file is empty or invalid']
            ];
        }

        // Normalize headers - trim whitespace and handle BOM
        $headers = array_map(function($header) {
            $header = trim($header);
            // Remove BOM if present
            if (substr($header, 0, 3) === "\xEF\xBB\xBF") {
                $header = substr($header, 3);
            }
            return trim($header);
        }, $headers);

        $data = [];
        $errors = [];
        $lineNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;
            
            // Trim empty values from the end of the row (common issue with Excel exports)
            while (!empty($row) && empty(trim(end($row)))) {
                array_pop($row);
            }
            
            // If row has more columns than headers, trim excess columns
            if (count($row) > count($headers)) {
                $row = array_slice($row, 0, count($headers));
            }
            
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }
            
            if (count($row) !== count($headers)) {
                $errors[] = "Line {$lineNumber}: Invalid number of columns (expected " . count($headers) . ", got " . count($row) . ")";
                continue;
            }

            $rowData = array_combine($headers, $row);
            $rowData = array_map('trim', $rowData);
            
            // Skip if all values are empty
            if (empty(array_filter($rowData))) {
                continue;
            }

            $data[] = $rowData;
        }

        fclose($handle);

        return [
            'success' => true,
            'data' => $data,
            'errors' => $errors
        ];
    }

    /**
     * Parse Excel file
     */
    protected function parseExcel($file): array
    {
        try {
            // Parse without WithHeadingRow to get original headers
            $rawData = Excel::toArray(new class implements ToArray {
                public function array(array $array): array
                {
                    return $array;
                }
            }, $file);

            if (empty($rawData) || empty($rawData[0]) || count($rawData[0]) < 2) {
                return [
                    'success' => false,
                    'errors' => ['Excel file is empty or invalid']
                ];
            }

            $allRows = $rawData[0];
            $headers = array_map('trim', $allRows[0]); // First row is headers
            
            if (empty($headers)) {
                return [
                    'success' => false,
                    'errors' => ['Excel file has no headers']
                ];
            }

            $errors = [];
            $parsedData = [];

            // Process data rows (skip header row)
            for ($i = 1; $i < count($allRows); $i++) {
                $row = $allRows[$i];
                $lineNumber = $i + 1; // +1 because array is 0-indexed but line numbers start at 1
                
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                // Ensure row has same number of columns as headers
                if (count($row) !== count($headers)) {
                    // Trim empty columns from end or pad with empty strings
                    if (count($row) > count($headers)) {
                        $row = array_slice($row, 0, count($headers));
                    } else {
                        $row = array_pad($row, count($headers), '');
                    }
                }

                // Combine headers with row values
                $rowData = [];
                foreach ($headers as $idx => $header) {
                    $value = isset($row[$idx]) ? $row[$idx] : '';
                    $rowData[$header] = is_null($value) ? '' : trim((string)$value);
                }

                $parsedData[] = $rowData;
            }

            return [
                'success' => true,
                'data' => $parsedData,
                'errors' => $errors
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'errors' => ['Failed to parse Excel file: ' . $e->getMessage()]
            ];
        }
    }
}

