<?php
// Debug RSS structure
$xml = simplexml_load_file('https://vnexpress.net/rss/kinh-doanh.rss', 'SimpleXMLElement', LIBXML_NOCDATA);

if ($xml && isset($xml->channel->item[0])) {
    $item = $xml->channel->item[0];
    
    echo "=== VNExpress RSS Structure Debug ===\n\n";
    
    echo "Item 0 Title: " . (string)$item->title . "\n\n";
    
    // Get description
    $desc = (string)$item->description;
    echo "Description HTML (first 1000 chars):\n";
    echo substr($desc, 0, 1000) . "\n\n";
    
    // Try to extract image from HTML
    if (preg_match('/<img[^>]+src=["\']?([^"\'>\s]+)["\']?/i', $desc, $matches)) {
        echo "Image found in description:\n";
        echo $matches[1] . "\n\n";
    } else {
        echo "No <img> tag found in description\n\n";
    }
    
    // List all child elements and attributes
    echo "All child elements:\n";
    foreach ($item->children() as $key => $val) {
        $str = (string)$val;
        echo "- {$key}: " . substr($str, 0, 100) . (strlen($str) > 100 ? '...' : '') . "\n";
    }
    
    echo "\n\nAll attributes in item:\n";
    $attrs = $item->attributes();
    if (count($attrs) > 0) {
        foreach ($attrs as $key => $val) {
            echo "- {$key}: {$val}\n";
        }
    } else {
        echo "No attributes\n";
    }
    
    echo "\n\nChecking for common image elements:\n";
    echo "- image tag exists: " . (isset($item->image) ? 'YES' : 'NO') . "\n";
    echo "- media:content exists: " . (isset($item->{'media:content'}) ? 'YES' : 'NO') . "\n";
    echo "- media:thumbnail exists: " . (isset($item->{'media:thumbnail'}) ? 'YES' : 'NO') . "\n";
    
} else {
    echo "Error loading RSS feed\n";
}
