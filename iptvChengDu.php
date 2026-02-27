<?php

$url = 'https://epg.51zmt.top:8001/multicast/api/channels/1/';
// 创建 stream context，忽略 HTTPS 证书验证（用于自签名或不可验证的证书）
$ssl_opts = [
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true,
    ]
];
$context = stream_context_create($ssl_opts);
$html = @file_get_contents($url, false, $context);
$json = json_decode($html, true);

if ($json['success'] != 1) {
    die('获取频道列表失败');
}   

$new_m3u = "#EXTM3U\n";
$new_m3u .= "#EXT-X-APP\n";
$new_m3u .= "#EXT-X-APTV-TYPE\n";
$new_m3u .= "#EXT-X-SUB-URL https://raw.githubusercontent.com/yikp/iTV/main/iptv.m3u\n";
$new_m3u .= "#EXT-X-GENERATED-TIME: " . date('Y-m-d H:i:s') . "\n\n";

$channels = $json['channels'];
foreach ($channels as $channel) {
    if (in_array($channel['video_info']['resolution'], ['FHD', 'UHD'])) {
        $resolution = $channel['video_info']['resolution'] === 'FHD' ? '高清' : '4K';
        $name = $channel['channel_name'];
        if (!fidter_name($name)) {
            continue; // 跳过含有直播或组播名字的频道
        }
        $logo_name = logo_name($name);
        $http_url = "http://10.10.1.77:7777/rtp/$channel[multicast_address]";
        $new_m3u .= "#EXTINF:-1 tvg-id=\"$logo_name\" tvg-name=\"$logo_name\" group-title=\"$resolution\", $name \n";
        $new_m3u .= "$http_url\n";
    }
}


file_put_contents('iptv.m3u', $new_m3u);
echo "\n新的M3U文件已保存为 iptv.m3u\n";

// 过滤掉含有直播和组播名字的频道
function fidter_name($name) {
    $keywords = ['直播', '组播'];
    foreach ($keywords as $keyword) {
        if (strpos($name, $keyword) !== false) {
            return false;
        }
    }
    return true;
}

// 处理频道name去适配主流的播放器，以匹配logo图标获取
function logo_name($name) {
    // 去掉末尾的“高清”、“专区”等字样
    $name = preg_replace('/高清|专区/i', '', $name);
    // 如果名字中包含cctv的话，去掉”-“
    $name = preg_replace('/CCTV-/i', 'cctv', $name);
    $name = preg_replace('/sctv6/i', 'sctv星空购物', $name);
    $name = preg_replace('/cctv少儿/i', 'cctv14', $name);
    $name = preg_replace('/＋/u', '+', $name);
    return $name;
}
