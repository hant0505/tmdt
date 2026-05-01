<?php
namespace Vendor\BusinessNews\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\CacheInterface;           // ← Thay thế Pool
use Magento\Framework\Serialize\SerializerInterface;

class Data extends AbstractHelper
{
    const CACHE_KEY = 'vnexpress_business_news';
    const CACHE_LIFETIME = 3600; // 1 giờ (3600 giây)

    protected $cache;
    protected $serializer;

    public function __construct(
        Context $context,
        CacheInterface $cache,                    // ← Dùng CacheInterface
        SerializerInterface $serializer           // ← Dùng để serialize an toàn
    ) {
        parent::__construct($context);
        $this->cache = $cache;
        $this->serializer = $serializer;
    }

    public function getBusinessNews(int $limit = 10): array
    {
        // Thử lấy từ cache trước
        $cachedData = $this->cache->load(self::CACHE_KEY);

        if ($cachedData) {
            return $this->serializer->unserialize($cachedData);
        }

        $rssUrl = 'https://vnexpress.net/rss/kinh-doanh.rss';
        $news = [];

        try {
            $xml = simplexml_load_file($rssUrl, 'SimpleXMLElement', LIBXML_NOCDATA);

            if ($xml && isset($xml->channel)) {
                $i = 0;
                foreach ($xml->channel->item as $item) {
                    if ($i >= $limit) {
                        break;
                    }
                    $news[] = [
                        'title'       => (string)$item->title,
                        'link'        => (string)$item->link,
                        'description' => strip_tags((string)$item->description),
                        'pubDate'     => date('d/m/Y H:i', strtotime((string)$item->pubDate)),
                    ];
                    $i++;
                }
            }
        } catch (\Exception $e) {
            $this->_logger->error('BusinessNews RSS Error: ' . $e->getMessage());
        }

        // Lưu vào cache
        if (!empty($news)) {
            $this->cache->save(
                $this->serializer->serialize($news),
                self::CACHE_KEY,
                ['vnexpress_business_news'],   // cache tag (dễ clear sau)
                self::CACHE_LIFETIME
            );
        }

        return $news;
    }
}