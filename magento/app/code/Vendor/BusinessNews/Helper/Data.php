<?php
namespace Vendor\BusinessNews\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;

class Data extends AbstractHelper
{
    const CACHE_KEY          = 'vnexpress_business_news';
    const CACHE_KEY_SIDEBAR  = 'vnexpress_market_news';   // cache riêng cho sidebar
    const CACHE_LIFETIME     = 3600;

    protected $cache;
    protected $serializer;

    public function __construct(
        Context $context,
        CacheInterface $cache,
        SerializerInterface $serializer
    ) {
        parent::__construct($context);
        $this->cache      = $cache;
        $this->serializer = $serializer;
    }

    // ──────────────────────────────────────────────────────────────
    // PUBLIC API
    // ──────────────────────────────────────────────────────────────

    /**
     * Bản tin kinh doanh chính (RSS kinh-doanh)
     * Dùng cho main list + gadgets
     */
    public function getBusinessNews(int $limit = 8): array
    {
        return $this->fetchRss(
            'https://vnexpress.net/rss/kinh-doanh.rss',
            self::CACHE_KEY,
            $limit
        );
    }

    /**
     * Bản tin thị trường (RSS thi-truong) — dùng riêng cho sidebar
     * Khác nguồn với kinh-doanh nên nội dung không trùng
     */
    public function getMarketNews(int $limit = 6): array
    {
        return $this->fetchRss(
            'https://vnexpress.net/rss/thi-truong.rss',
            self::CACHE_KEY_SIDEBAR,
            $limit
        );
    }

    // ──────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ──────────────────────────────────────────────────────────────

    /**
     * Generic RSS fetcher với cache
     */
    private function fetchRss(string $rssUrl, string $cacheKey, int $limit): array
    {
        // 1. Thử cache
        $cached = $this->cache->load($cacheKey);
        if ($cached) {
            $all = $this->serializer->unserialize($cached);
            return array_slice($all, 0, $limit);
        }

        // 2. Fetch RSS
        $news = [];
        try {
            $xml = simplexml_load_file($rssUrl, 'SimpleXMLElement', LIBXML_NOCDATA);

            if ($xml && isset($xml->channel)) {
                foreach ($xml->channel->item as $item) {
                    $title = $this->toUtf8((string)$item->title);
                    $news[] = [
                        'title'       => $title,
                        'image'       => $this->extractImageFromItem($item),
                        'description' => $this->cleanDescription((string)$item->description),
                        'date'        => $this->formatDate((string)$item->pubDate),
                        'link'        => (string)$item->link,
                    ];
                }
            }
        } catch (\Exception $e) {
            $this->_logger->error("BusinessNews RSS Error [{$rssUrl}]: " . $e->getMessage());
        }

        // 3. Lưu toàn bộ vào cache (không giới hạn limit ở đây)
        if (!empty($news)) {
            try {
                $this->cache->save(
                    $this->serializer->serialize($news),
                    $cacheKey,
                    [$cacheKey],
                    self::CACHE_LIFETIME
                );
            } catch (\Exception $e) {
                $this->_logger->warning('Cache save error: ' . $e->getMessage());
            }
        }

        return array_slice($news, 0, $limit);
    }

    /**
     * Extract ảnh từ item RSS
     * VNExpress nhúng ảnh trong HTML của <description>
     */
    private function extractImageFromItem(\SimpleXMLElement $item): string
    {
        try {
            // 1. <img> trong description HTML
            $description = (string)$item->description;
            if (preg_match('/<img[^>]+src=["\']?([^"\'>\s]+)["\']?/i', $description, $m)) {
                return $this->toUtf8((string)$m[1]);
            }

            // 2. media:content namespace
            $ns = $item->getNamespaces(true);
            if (isset($ns['media'])) {
                $media = $item->children($ns['media']);
                if (isset($media->content[0])) {
                    $attrs = $media->content[0]->attributes();
                    if (isset($attrs['url'])) return (string)$attrs['url'];
                }
                if (isset($media->thumbnail)) {
                    $attrs = $media->thumbnail->attributes();
                    if (isset($attrs['url'])) return (string)$attrs['url'];
                }
            }

            // 3. <image> tag
            if (isset($item->image)) return (string)$item->image;

        } catch (\Exception $e) {
            $this->_logger->warning('Error extracting image: ' . $e->getMessage());
        }

        return 'https://via.placeholder.com/600x400?text=No+Image';
    }

    /**
     * Bỏ HTML, giới hạn 160 ký tự
     */
    private function cleanDescription(string $description): string
    {
        $text = strip_tags($description);
        $text = $this->toUtf8($text);
        $text = trim(preg_replace('/\s+/', ' ', $text));

        if (mb_strlen($text, 'UTF-8') > 160) {
            $text = mb_substr($text, 0, 160, 'UTF-8') . '...';
        }
        return $text;
    }

    /**
     * Format ngày: "Sat, 25 Apr 2026 15:08:00 +0700" → "25/04/2026"
     */
    private function formatDate(string $dateString): string
    {
        $ts = strtotime($dateString);
        return $ts !== false ? date('d/m/Y', $ts) : date('d/m/Y');
    }

    private function toUtf8(string $str): string
    {
        return mb_check_encoding($str, 'UTF-8')
            ? $str
            : mb_convert_encoding($str, 'UTF-8', 'auto');
    }
}
