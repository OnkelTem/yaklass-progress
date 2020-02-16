<?php

namespace Yaklass\Helpers;

use Exception;
use Google_Client;
use Google_Exception;
use Google_Service_Sheets;
use Google_Service_Sheets_BatchUpdateSpreadsheetRequest;
use Google_Service_Sheets_BatchUpdateSpreadsheetResponse;
use Google_Service_Sheets_Request;
use Google_Service_Sheets_Spreadsheet;
use Google_Service_Sheets_UpdateCellsRequest;
use Google_Service_Sheets_UpdateValuesResponse;
use Google_Service_Sheets_ValueRange;

class GoogleSheetsHelper {

  /**
   * @var Google_Service_Sheets
   */
  private $service;
  private $appName;
  private $credentials;
  private $spreadsheetId;
  /**
   * @var array
   */
  private $updateRequests = [];
  private $sheets = [];

  /**
   * GoogleSheetWriter constructor.
   * @param $options
   * @throws Exception
   */
  public function __construct($options) {
    $this->appName = $options['appName'];
    $this->credentials = $options['credentials'];
    $this->spreadsheetId = $options['spreadsheetId'];
    $this->service = $this->init();
  }

  /**
   * @return Google_Service_Sheets
   * @throws Exception
   */
  protected function init() {
    $client = new Google_Client();
    $client->setApplicationName($this->appName);
    $client->setScopes([Google_Service_Sheets::SPREADSHEETS]);
    $client->setAccessType('offline');
    try {
      $client->setAuthConfig($this->credentials);
    }
    catch (Google_Exception $e) {
      throw new Exception($e->getMessage());
    }
    return new Google_Service_Sheets($client);
  }

  public function getSheet($title) {
    if (!array_key_exists($title, $this->sheets)) {
      /** @var Google_Service_Sheets_Spreadsheet $spreadsheet */
      $spreadsheet = $this->service->spreadsheets->get($this->spreadsheetId);
      $obj = $spreadsheet->toSimpleObject();
      $sheet = current(array_filter($obj->sheets, function($sheet) use ($title) {
        return $sheet->properties->title == $title;
      }));
      $this->sheets[$title] = $sheet;
    }
    return $this->sheets[$title];
  }

  /**
   * @param $title
   * @return Google_Service_Sheets_BatchUpdateSpreadsheetResponse
   * @throws Exception
   */
  public function resetSheet($title) {
    $sheet = $this->getSheet($title);
    // Remove frozen row
    if (isset($sheet->properties)) {
      if (isset($sheet->properties->gridProperties->frozenRowCount)) {
        $this->queueUpdate($this->requestFrozenRow($title, 0), 'clear');
      }
    }
    // Remove merges
    if (isset($sheet->merges)) {
      foreach($sheet->merges as $merge) {
        $this->queueUpdate($this->requestUnmerge($title,
          $merge->startRowIndex,
          $merge->startColumnIndex,
          $merge->endRowIndex - 1,
          $merge->endColumnIndex - 1), 'clear');
      }
    }
    // Show all columns
    $this->queueUpdate($this->requestDimensionsHide($title, 'COLUMNS', FALSE));
    // Remove data
    $this->queueUpdate($this->requestClearData($title), 'clear');
    return $this->applyUpdates('clear');
  }

  /**
   * @param string $queue_name
   * @return Google_Service_Sheets_BatchUpdateSpreadsheetResponse
   * @throws Exception
   */
  public function applyUpdates($queue_name = 'default') {
    if (!array_key_exists($queue_name, $this->updateRequests)) {
      throw new Exception("Queue not found: $queue_name");
    }
    $batchUpdateRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest();
    $batchUpdateRequest->setRequests($this->updateRequests[$queue_name]);
    unset($this->updateRequests[$queue_name]);
    return $this->service->spreadsheets->batchUpdate(
      $this->spreadsheetId, $batchUpdateRequest);
  }

  /**
   * @param $title
   * @param $data
   * @return Google_Service_Sheets_UpdateValuesResponse
   */
  public function write($title, $data) {
    $range = $title . '!A1';
    $values = new Google_Service_Sheets_ValueRange([
      'range' => $range,
      'majorDimension' => 'ROWS',
      'values' => $data]);
    return $this->service->spreadsheets_values->update(
      $this->spreadsheetId, $range, $values, ['valueInputOption' => 'RAW']);
  }

  /**
   * @param $update
   * @param string $queue_name
   */
  public function queueUpdate($update, $queue_name = 'default') {
    $this->updateRequests[$queue_name][] = $update;
  }

  public function requestMerge($title, $r1, $c1, $r2, $c2) {
    return new Google_Service_Sheets_Request([
      'mergeCells' => [
        'range' => [
          'sheetId' => $this->getSheet($title)->properties->sheetId,
          "startRowIndex" => $r1,
          "endRowIndex" => $r2 + 1,
          "startColumnIndex" => $c1,
          "endColumnIndex" => $c2 + 1,
        ],
        'mergeType' => 'MERGE_ALL'
      ]
    ]);
  }

  public function requestUnmerge($title, $r1, $c1, $r2, $c2) {
    return new Google_Service_Sheets_Request([
      'unmergeCells' => [
        'range' => [
          'sheetId' => $this->getSheet($title)->properties->sheetId,
          "startRowIndex" => $r1,
          "endRowIndex" => $r2 + 1,
          "startColumnIndex" => $c1,
          "endColumnIndex" => $c2 + 1,
        ]
      ]
    ]);
  }

  public function requestFrozenRow($title, $count) {
    return new Google_Service_Sheets_Request([
      'updateSheetProperties' => [
        'properties' => [
          'sheetId' => $this->getSheet($title)->properties->sheetId,
          'gridProperties' => [
            'frozenRowCount' => $count,
          ]
        ],
        'fields' => 'gridProperties.frozenRowCount'
      ]
    ]);
  }

  public function requestFrozenColumn($title, $count) {
    return new Google_Service_Sheets_Request([
      'updateSheetProperties' => [
        'properties' => [
          'sheetId' => $this->getSheet($title)->properties->sheetId,
          'gridProperties' => [
            'frozenColumnCount' => $count,
          ]
        ],
        'fields' => 'gridProperties.frozenColumnCount'
      ]
    ]);
  }

  public function requestClearData($title) {
    return new Google_Service_Sheets_UpdateCellsRequest([
      'updateCells' => [
        'range' => [
          'sheetId' => $this->getSheet($title)->properties->sheetId,
        ],
        'fields' => '*'
      ]
    ]);
  }

  public function requestDimensionsHide($title, $dimension = 'COLUMNS', $hide = TRUE, $start_index = NULL, $count = NULL) {
    return new Google_Service_Sheets_Request([
      'updateDimensionProperties' => [
        'range' => [
          'sheetId' => $this->getSheet($title)->properties->sheetId,
          'dimension' => $dimension,
        ] + (!is_null($start_index) && $count > 0 ? [
          'startIndex' => $start_index,
          'endIndex' => $start_index + $count,
        ] : []),
        'properties' => [
          'hiddenByUser' => $hide,
        ],
        'fields' => 'hiddenByUser'
      ]
    ]);
  }

  public function requestDimensionsAutoResize($title, $dimension = 'COLUMNS', $start_index = NULL, $count = NULL) {
    return new Google_Service_Sheets_Request([
      'autoResizeDimensions' => [
        'dimensions' => [
          'sheetId' => $this->getSheet($title)->properties->sheetId,
          'dimension' => $dimension,
          ] + (!is_null($start_index) && $count > 0 ? [
            'startIndex' => $start_index,
            'endIndex' => $start_index + $count,
          ] : []),
      ]
    ]);
  }

  /**
   * @param $title
   * @param $format
   * @param array $range
   * @return Google_Service_Sheets_UpdateCellsRequest
   * @see https://developers.google.com/sheets/api/reference/rest/v4/spreadsheets/cells#cellformat
   */
  public function requestFormat($title, $format, $range = []) {
    return new Google_Service_Sheets_UpdateCellsRequest([
      'repeatCell' => [
        'range' => $this->getRange($title, $range),
        'cell' => [
          'userEnteredFormat' => $format,
        ],
        'fields' => sprintf('userEnteredFormat(%s)', implode(',', array_keys($format)))
      ]
    ]);
  }

  protected function getRange($title, $range) {
    $result['sheetId'] = $this->getSheet($title)->properties->sheetId;
    if (!empty($range)) {
      $result += [
        "startRowIndex" => $range['r1'],
        "endRowIndex" => $range['r2'] + 1,
        "startColumnIndex" => $range['c1'],
        "endColumnIndex" => $range['c2'] + 1,
      ];
    }
    return $result;
  }

  public function getColor($hex_str) {
    $str = substr($hex_str, 1);
    $code = hexdec($str);
    $b = $code % 256;
    $code = $code >> 8;
    $g = $code % 256;
    $code = $code >> 8;
    $r = $code % 256;
    return [
      'red' => $r / 255,
      'green' => $g / 255,
      'blue' => $b / 255,
    ];
  }
}
