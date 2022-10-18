<?php

$downloadImages = true;

include 'config.php';

$mids = array('Newell Test' => 110007108);

foreach ($mids as $mid => $midNumber) {

    $bu = $mid;

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL            => 'https://mctc659s6fvgc5-vg35769jt9gsm.auth.marketingcloudapis.com/v2/token',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_MAXREDIRS      => 10,
        CURLOPT_TIMEOUT        => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => '{
    "grant_type": "client_credentials",
    "client_id": "'.$client_id.'",
    "client_secret": "'.$client_secret.'",
    "account_id": "' . $midNumber . '"
}',
        CURLOPT_HTTPHEADER     => array(
            'Content-Type: application/json',
        ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    $response = json_decode($response, true);
    $token    = $response['access_token'];

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL            => 'https://mctc659s6fvgc5-vg35769jt9gsm.rest.marketingcloudapis.com/asset/v1/content/categories?$page=1&$pagesize=500',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_MAXREDIRS      => 10,
        CURLOPT_TIMEOUT        => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST  => 'GET',
        CURLOPT_HTTPHEADER     => array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);

    $response = json_decode($response, true);

    $folders = $response['items'];

    $new = array();

    foreach ($folders as $a) {
        $new[$a['parentId']][] = $a;
    }
    $tree = createTree($new, $new[0]); // changed

    $folders = $tree;

    $lastPath = $bu;

    createFolders($folders, $lastPath, $allFolders, $token);

    writeSQLqueries($bu, $token);

    getDEChanges($bu, $token);

}

function createTree(&$list, $parent)
{
    $tree = array();
    foreach ($parent as $k => $l) {
        if (isset($list[$l['id']])) {
            $l['folders'] = createTree($list, $list[$l['id']]);
        }
        $tree[] = $l;
    }
    return $tree;
}

function createFolders($folders, $lastPath, &$allFolders, $token)
{

    foreach ($folders as $folder) {

        $name     = $folder['name'];
        $folders  = $folder['folders'];
        $id       = $folder['id'];
        $combined = $lastPath . '/' . $name;

        echo $combined . '(' . $id . ')<br/>';

        if (!file_exists($combined)) {
            mkdir($combined, 0777, true);
        }

        writeContent($id, $combined, $token);

        if ($folders) {
            createFolders($folders, $combined, $allFolders, $token);
        }

    }
}

function writeContent($folderID, $contentPath, $token)
{

    if (strpos($contentPath, '2020') !== false || strpos($contentPath, '01 January') !== false) {
        echo '<span style="color:red;">Skipped - Content is too old.</span>';
        echo '<br/>';
    } else {

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL            => 'https://mctc659s6fvgc5-vg35769jt9gsm.rest.marketingcloudapis.com/asset/v1/content/assets?$page=1&$pagesize=50&$orderBy=name%20asc&$filter=category.id=' . $folderID,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_HTTPHEADER     => array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $response = json_decode($response, true);

        $items = $response['items'];

        foreach ($items as $item) {

            $assetType   = $item['assetType'];
            $displayName = $item['assetType']['displayName'];
            $category    = $item['category']['name'];
            $name        = $item['name'];

            if ($displayName == 'Image' && $downloadImages == true) {

                $fileName     = $item['fileProperties']['fileName'];
                $publishedURL = $item['fileProperties']['publishedURL'];
                if (file_exists($contentPath . "/" . $fileName)) {
                    echo '<span style="color:red">Image Exists: ' . $contentPath . "/" . $fileName . '</span><br/>';

                } else {
                    echo '<span style="color:green">Image Added: ' . $contentPath . "/" . $fileName . '</span><br/>';
                    file_put_contents($contentPath . "/" . $fileName, file_get_contents($publishedURL));
                }

            } elseif ($displayName == 'HTML Email' || $displayName == 'Template-Based Email') {

                $content = $item['views']['html']['content'];
                $fp      = fopen($contentPath . "/" . $name . ".html", "wb");
                fwrite($fp, $content);
                fclose($fp);

            } elseif ($displayName == 'HTML Block') {

                $content = $item['views']['content'];
                $fp      = fopen($contentPath . "/" . $name . ".html", "wb");
                fwrite($fp, $content);
                fclose($fp);

            } elseif ($displayName == 'Free Form Block') {

                $content = $item['content'];
                $fp      = fopen($contentPath . "/" . $name . ".html", "wb");
                fwrite($fp, $content);
                fclose($fp);

            }

        }
    }

}

function writeSQLqueries($bu, $token)
{

    $contentPath = $bu . '/Automation Studio/SQL Query/';
    if (!file_exists($contentPath)) {
        mkdir($contentPath, 0777, true);
    }
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL            => 'https://mctc659s6fvgc5-vg35769jt9gsm.rest.marketingcloudapis.com/automation/v1/queries/?$page=1&$pageSize=500',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_MAXREDIRS      => 10,
        CURLOPT_TIMEOUT        => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST  => 'GET',
        CURLOPT_HTTPHEADER     => array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);

    $response = json_decode($response, true);

    $items = $response['items'];

    foreach ($items as $item) {

        $name      = $item['name'];
        $queryText = $item['queryText'];
        $fp        = fopen($contentPath . "/" . $name . ".sql", "wb");
        fwrite($fp, $queryText);
        fclose($fp);

    }
}

function getDEChanges($bu, $token)
{

    $contentPath = $bu . '/Data Extensions/';
    if (!file_exists($contentPath)) {
        mkdir($contentPath, 0777, true);
    }
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL            => 'https://mctc659s6fvgc5-vg35769jt9gsm.rest.marketingcloudapis.com/data/v1/customobjectdata/key/Master_Data_Extension_Inventory/rowset',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_MAXREDIRS      => 10,
        CURLOPT_TIMEOUT        => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST  => 'GET',
        CURLOPT_HTTPHEADER     => array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);

    $response = json_decode($response, true);

    $items = $response['items'];
    if ($items) {

        foreach ($items as $item) {

            $name      = $item['keys']['name'];
            $list = $item['values']['fields'];
            $rowcount = $item['values']['rowcount'];

            $name = $name . ' ' . '(' . $rowcount . ')';
            $list = json_decode($list, true);

            print_r($list);
            echo '<br/><br/>';
            $fp = fopen($contentPath . "/" . $name . ".csv", 'w'); 
  
// Loop through file pointer and a line 
foreach ($list as $fields) { 
    fputcsv($fp, $fields); 
} 
  
fclose($fp); 



            // $fp        = fopen($contentPath . "/" . $name . ".txt", "wb");
            // fwrite($fp, $fields);
            // fclose($fp);

        }
    }
}

exit;
