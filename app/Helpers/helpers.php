<?php

use App\Models\Poem;

function poem_slug(Poem $poem)
{
    return $poem->poem_id . '-' . name2slug($poem->name);
}

function name2slug($name)
{
    $name = str_replace([' ', '/', '-'], ['', '_', '_'], $name);

    return mb_substr($name, 0, 60);
}

if (!function_exists('generateTokenUrl')) {
    /**
     * 生成token并返回完整URL
     * @param string $fullUrl 输入的完整URL (e.g., "https://app24.guwendao.net/router/shiwen/shiwenList2409.aspx?page=1&tag=")
     * @return string 返回带新token的完整URL
     */
    function generateTokenUrl($fullUrl)
    {
        $key = "token3d402cc19cdc";

        // 解析URL
        $parsedUrl = parse_url($fullUrl);
        $scheme = $parsedUrl['scheme'] ?? 'https';
        $host = $parsedUrl['host'] ?? 'app24.guwendao.net';
        $path = $parsedUrl['path'] ?? '';
        $query = $parsedUrl['query'] ?? '';

        // 解析query (decode value)
        parse_str($query, $params);

        // 去token参数
        unset($params['token']);

        // 提取文件名 (去目录)
        $filename = basename($path);

        // 参数值拼接 (sorted keys order , raw value , 忽略空)
        $valuesConcat = '';
        if (!empty($params)) {
            ksort($params);  # sorted by key
            foreach ($params as $v) {
                $vStr = (string) $v;
                if (!empty($vStr)) {
                    $valuesConcat .= $vStr;
                }
            }
        }

        // input = 文件名 + 值拼接 + key
        $inputStr = $filename . $valuesConcat . $key;

        // MD5.upper
        // $token = strtoupper(bin2hex(md5($inputStr, true)));
        $token = strtoupper(md5($inputStr));

        // 重建query (encode value , sorted keys)
        ksort($params);
        $queryParts = [];
        foreach ($params as $k => $v) {
            $queryParts[] = urlencode($k) . '=' . urlencode($v);
        }
        $newQuery = implode('&', $queryParts);
        $newQuery = $newQuery ? $newQuery . '&token=' . $token : 'token=' . $token;

        // 完整URL
        $fullUrlGen = $scheme . '://' . $host . $path . '?' . $newQuery;

        return $fullUrlGen;
    }
}