<?php
/**
 * RssImportValidator Class
 * Validates RSS feeds before import to ensure quality and compatibility
 * 
 * IMPORTANT: This class is SEPARATE from RssFeedParser and does not modify it
 * It performs pre-import validation checks only
 */

require_once __DIR__ . '/../config/config.php';

class RssImportValidator
{
    // Validation result constants
    const VALIDATION_PASS = 'pass';
    const VALIDATION_WARNING = 'warning';
    const VALIDATION_FAIL = 'fail';
    
    // Image dimension requirements
    const IMAGE_MIN_SIZE = 1400;
    const IMAGE_MAX_SIZE = 3000;
    const IMAGE_RECOMMENDED_MIN = 1400;
    
    // Response time thresholds (seconds)
    const RESPONSE_TIME_WARNING = 5;
    
    // Timeout for validation checks (shorter than main fetch)
    private $validationTimeout = 8;
    
    /**
     * Main validation method - validates RSS feed comprehensively
     * 
     * @param string $feedUrlToValidate The RSS feed URL to validate
     * @return array Validation results with structure:
     *   - can_import: bool (whether import should be allowed)
     *   - validation_level: string (pass/warning/fail)
     *   - critical_checks: array (must-pass checks)
     *   - warning_checks: array (should-pass checks)
     *   - feed_metadata: array (basic feed info)
     *   - validation_errors: array (blocking errors)
     *   - validation_warnings: array (non-blocking warnings)
     */
    public function validateFeedForImport($feedUrlToValidate)
    {
        $validationResults = [
            'can_import' => false,
            'validation_level' => self::VALIDATION_FAIL,
            'critical_checks' => [],
            'warning_checks' => [],
            'feed_metadata' => [],
            'validation_errors' => [],
            'validation_warnings' => [],
            'response_time_seconds' => 0
        ];
        
        try {
            // Track response time
            $startValidationTime = microtime(true);
            
            // CRITICAL CHECK 1: URL Format
            $urlFormatCheck = $this->checkUrlFormat($feedUrlToValidate);
            $validationResults['critical_checks'][] = $urlFormatCheck;
            if (!$urlFormatCheck['passed']) {
                $validationResults['validation_errors'][] = $urlFormatCheck;
                return $validationResults;
            }
            
            // CRITICAL CHECK 2: Feed Accessibility
            $accessibilityCheck = $this->checkFeedAccessibility($feedUrlToValidate);
            $validationResults['critical_checks'][] = $accessibilityCheck;
            if (!$accessibilityCheck['passed']) {
                $validationResults['validation_errors'][] = $accessibilityCheck;
                return $validationResults;
            }
            
            // Fetch feed content for further validation
            $feedXmlContent = $this->fetchFeedForValidation($feedUrlToValidate);
            if (!$feedXmlContent) {
                $validationResults['validation_errors'][] = [
                    'check_name' => 'feed_fetch',
                    'passed' => false,
                    'message' => 'Unable to fetch feed content',
                    'details' => 'Feed URL returned empty content',
                    'suggestion' => 'Verify the feed URL is correct and accessible'
                ];
                return $validationResults;
            }
            
            // CRITICAL CHECK 3: XML Structure
            $xmlStructureCheck = $this->checkXmlStructure($feedXmlContent);
            $validationResults['critical_checks'][] = $xmlStructureCheck;
            if (!$xmlStructureCheck['passed']) {
                $validationResults['validation_errors'][] = $xmlStructureCheck;
                return $validationResults;
            }
            
            // Parse XML for further checks
            $parsedXmlForValidation = $xmlStructureCheck['parsed_xml'];
            
            // CRITICAL CHECK 4: Required RSS Fields
            $requiredFieldsCheck = $this->checkRequiredRssFields($parsedXmlForValidation);
            $validationResults['critical_checks'][] = $requiredFieldsCheck;
            if (!$requiredFieldsCheck['passed']) {
                $validationResults['validation_errors'][] = $requiredFieldsCheck;
                return $validationResults;
            }
            
            // CRITICAL CHECK 5: Episodes Exist
            $episodesCheck = $this->checkEpisodesExist($parsedXmlForValidation);
            $validationResults['critical_checks'][] = $episodesCheck;
            if (!$episodesCheck['passed']) {
                $validationResults['validation_errors'][] = $episodesCheck;
                return $validationResults;
            }
            
            // Extract cover image URL
            $coverImageUrl = $this->extractCoverImageUrl($parsedXmlForValidation);
            
            // CRITICAL CHECK 6: Cover Image Exists
            $imageExistsCheck = $this->checkCoverImageExists($coverImageUrl);
            $validationResults['critical_checks'][] = $imageExistsCheck;
            if (!$imageExistsCheck['passed']) {
                $validationResults['validation_errors'][] = $imageExistsCheck;
                return $validationResults;
            }
            
            // CRITICAL CHECK 7: Cover Image Validation
            $imageValidationCheck = $this->checkCoverImageValidation($coverImageUrl);
            $validationResults['critical_checks'][] = $imageValidationCheck;
            if (!$imageValidationCheck['passed']) {
                $validationResults['validation_errors'][] = $imageValidationCheck;
                return $validationResults;
            }
            
            // All critical checks passed!
            $validationResults['can_import'] = true;
            $validationResults['validation_level'] = self::VALIDATION_PASS;
            
            // Now run warning-level checks (non-blocking)
            
            // WARNING CHECK 1: iTunes Namespace
            $itunesNamespaceCheck = $this->checkItunesNamespace($parsedXmlForValidation);
            $validationResults['warning_checks'][] = $itunesNamespaceCheck;
            if (!$itunesNamespaceCheck['passed']) {
                $validationResults['validation_warnings'][] = $itunesNamespaceCheck;
                $validationResults['validation_level'] = self::VALIDATION_WARNING;
            }
            
            // WARNING CHECK 2: iTunes Tags
            $itunesTagsCheck = $this->checkItunesTags($parsedXmlForValidation);
            $validationResults['warning_checks'][] = $itunesTagsCheck;
            if (!$itunesTagsCheck['passed']) {
                $validationResults['validation_warnings'][] = $itunesTagsCheck;
                $validationResults['validation_level'] = self::VALIDATION_WARNING;
            }
            
            // WARNING CHECK 3: Image Size Recommendation
            if (isset($imageValidationCheck['image_dimensions'])) {
                $imageSizeCheck = $this->checkImageSizeRecommendation($imageValidationCheck['image_dimensions']);
                $validationResults['warning_checks'][] = $imageSizeCheck;
                if (!$imageSizeCheck['passed']) {
                    $validationResults['validation_warnings'][] = $imageSizeCheck;
                    $validationResults['validation_level'] = self::VALIDATION_WARNING;
                }
            }
            
            // WARNING CHECK 4: Response Time
            $responseTime = microtime(true) - $startValidationTime;
            $validationResults['response_time_seconds'] = round($responseTime, 2);
            $responseTimeCheck = $this->checkResponseTime($responseTime);
            $validationResults['warning_checks'][] = $responseTimeCheck;
            if (!$responseTimeCheck['passed']) {
                $validationResults['validation_warnings'][] = $responseTimeCheck;
                $validationResults['validation_level'] = self::VALIDATION_WARNING;
            }
            
            // WARNING CHECK 5: PubDate on Items
            $pubDateCheck = $this->checkItemPubDates($parsedXmlForValidation);
            $validationResults['warning_checks'][] = $pubDateCheck;
            if (!$pubDateCheck['passed']) {
                $validationResults['validation_warnings'][] = $pubDateCheck;
                $validationResults['validation_level'] = self::VALIDATION_WARNING;
            }
            
            // Extract feed metadata
            $validationResults['feed_metadata'] = $this->extractFeedMetadataForValidation($parsedXmlForValidation, $coverImageUrl, $episodesCheck);
            
            return $validationResults;
            
        } catch (Exception $e) {
            $validationResults['validation_errors'][] = [
                'check_name' => 'exception',
                'passed' => false,
                'message' => 'Validation exception occurred',
                'details' => $e->getMessage(),
                'suggestion' => 'Please try again or contact support'
            ];
            return $validationResults;
        }
    }
    
    /**
     * Check URL format validity
     */
    private function checkUrlFormat($urlToCheck)
    {
        $isValidFormat = filter_var($urlToCheck, FILTER_VALIDATE_URL) !== false;
        
        return [
            'check_name' => 'url_format',
            'passed' => $isValidFormat,
            'message' => $isValidFormat ? 'Valid URL format' : 'Invalid URL format',
            'details' => $isValidFormat ? 'URL structure is valid' : 'URL must start with http:// or https://',
            'suggestion' => $isValidFormat ? '' : 'Check that the URL is complete and properly formatted'
        ];
    }
    
    /**
     * Check feed accessibility (HTTP status)
     */
    private function checkFeedAccessibility($urlToCheck)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $urlToCheck,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $this->validationTimeout,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_NOBODY => true, // HEAD request only
            CURLOPT_SSL_VERIFYPEER => false, // Lenient for validation
            CURLOPT_USERAGENT => 'PodFeed-Validator/1.0'
        ]);
        
        curl_exec($ch);
        $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrorMsg = curl_error($ch);
        curl_close($ch);
        
        $isAccessible = ($httpStatusCode === 200);
        
        return [
            'check_name' => 'feed_accessibility',
            'passed' => $isAccessible,
            'message' => $isAccessible ? 'Feed is accessible' : 'Feed is not accessible',
            'details' => $isAccessible ? "HTTP {$httpStatusCode} OK" : "HTTP {$httpStatusCode}" . ($curlErrorMsg ? ": {$curlErrorMsg}" : ''),
            'suggestion' => $isAccessible ? '' : 'Verify the feed URL is publicly accessible and not behind authentication'
        ];
    }
    
    /**
     * Fetch feed content for validation (separate from main parser)
     */
    private function fetchFeedForValidation($urlToFetch)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $urlToFetch,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $this->validationTimeout,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'PodFeed-Validator/1.0'
        ]);
        
        $contentFetched = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$contentFetched) {
            return false;
        }
        
        return $contentFetched;
    }
    
    /**
     * Check XML structure validity
     */
    private function checkXmlStructure($xmlContentToCheck)
    {
        libxml_use_internal_errors(true);
        libxml_clear_errors();
        
        $xmlParsed = simplexml_load_string($xmlContentToCheck);
        $xmlErrors = libxml_get_errors();
        libxml_clear_errors();
        
        $isValidXml = ($xmlParsed !== false && empty($xmlErrors));
        
        $errorDetails = '';
        if (!empty($xmlErrors)) {
            $firstError = $xmlErrors[0];
            $errorDetails = "Line {$firstError->line}: {$firstError->message}";
        }
        
        return [
            'check_name' => 'xml_structure',
            'passed' => $isValidXml,
            'message' => $isValidXml ? 'Valid XML structure' : 'Invalid XML structure',
            'details' => $isValidXml ? 'XML parses correctly' : $errorDetails,
            'suggestion' => $isValidXml ? '' : 'Validate feed at https://validator.w3.org/feed/',
            'parsed_xml' => $xmlParsed // Pass along for further checks
        ];
    }
    
    /**
     * Check required RSS fields
     */
    private function checkRequiredRssFields($xmlToCheck)
    {
        $missingFields = [];
        
        // Check for channel element
        if (!isset($xmlToCheck->channel)) {
            return [
                'check_name' => 'required_fields',
                'passed' => false,
                'message' => 'Missing <channel> element',
                'details' => 'RSS feeds must have a <channel> element',
                'suggestion' => 'Ensure feed follows RSS 2.0 or Atom format'
            ];
        }
        
        $channelElement = $xmlToCheck->channel;
        
        // Check required fields
        if (empty($channelElement->title)) $missingFields[] = 'title';
        if (empty($channelElement->link)) $missingFields[] = 'link';
        if (empty($channelElement->description)) $missingFields[] = 'description';
        
        $allFieldsPresent = empty($missingFields);
        
        return [
            'check_name' => 'required_fields',
            'passed' => $allFieldsPresent,
            'message' => $allFieldsPresent ? 'All required fields present' : 'Missing required fields',
            'details' => $allFieldsPresent ? 'title, link, description found' : 'Missing: ' . implode(', ', $missingFields),
            'suggestion' => $allFieldsPresent ? '' : 'Add missing fields to your RSS feed'
        ];
    }
    
    /**
     * Check that episodes exist
     */
    private function checkEpisodesExist($xmlToCheck)
    {
        $itemElements = $xmlToCheck->channel->item ?? [];
        $episodeCount = count($itemElements);
        $hasEpisodes = ($episodeCount > 0);
        
        return [
            'check_name' => 'episodes_exist',
            'passed' => $hasEpisodes,
            'message' => $hasEpisodes ? "{$episodeCount} episode(s) found" : 'No episodes found',
            'details' => $hasEpisodes ? "Feed contains {$episodeCount} <item> elements" : 'Feed must have at least one <item> element',
            'suggestion' => $hasEpisodes ? '' : 'Publish at least one episode before importing',
            'episode_count' => $episodeCount
        ];
    }
    
    /**
     * Extract cover image URL from feed
     */
    private function extractCoverImageUrl($xmlToExtractFrom)
    {
        // Try iTunes image first
        $itunesNamespaces = $xmlToExtractFrom->getNamespaces(true);
        if (isset($itunesNamespaces['itunes'])) {
            $itunesImage = $xmlToExtractFrom->channel->children($itunesNamespaces['itunes'])->image;
            if ($itunesImage) {
                $imageHref = (string)$itunesImage->attributes()->href;
                if (!empty($imageHref)) {
                    return $imageHref;
                }
            }
        }
        
        // Try standard RSS image
        if (isset($xmlToExtractFrom->channel->image->url)) {
            return (string)$xmlToExtractFrom->channel->image->url;
        }
        
        return null;
    }
    
    /**
     * Check cover image exists
     */
    private function checkCoverImageExists($imageUrlToCheck)
    {
        if (empty($imageUrlToCheck)) {
            return [
                'check_name' => 'cover_image_exists',
                'passed' => false,
                'message' => 'Cover image not found',
                'details' => 'No <image> or <itunes:image> tag found in feed',
                'suggestion' => 'Add cover image to your RSS feed (required for podcast directories)'
            ];
        }
        
        return [
            'check_name' => 'cover_image_exists',
            'passed' => true,
            'message' => 'Cover image URL found',
            'details' => 'Image URL present in feed',
            'suggestion' => ''
        ];
    }
    
    /**
     * Validate cover image (accessibility, format, dimensions)
     */
    private function checkCoverImageValidation($imageUrlToValidate)
    {
        if (empty($imageUrlToValidate)) {
            return [
                'check_name' => 'cover_image_validation',
                'passed' => false,
                'message' => 'No image to validate',
                'details' => 'Image URL is empty',
                'suggestion' => ''
            ];
        }
        
        // Check if image is accessible
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $imageUrlToValidate,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_NOBODY => true,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return [
                'check_name' => 'cover_image_validation',
                'passed' => false,
                'message' => 'Cover image not accessible',
                'details' => "HTTP {$httpCode} - Image URL returns error",
                'suggestion' => 'Ensure image URL is publicly accessible'
            ];
        }
        
        // Check image format
        $validImageTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $isValidType = false;
        foreach ($validImageTypes as $validType) {
            if (stripos($contentType, $validType) !== false) {
                $isValidType = true;
                break;
            }
        }
        
        if (!$isValidType) {
            return [
                'check_name' => 'cover_image_validation',
                'passed' => false,
                'message' => 'Invalid image format',
                'details' => "Content-Type: {$contentType}",
                'suggestion' => 'Use JPG, PNG, GIF, or WebP format'
            ];
        }
        
        // Get image dimensions
        $imageDimensions = @getimagesize($imageUrlToValidate);
        if (!$imageDimensions) {
            return [
                'check_name' => 'cover_image_validation',
                'passed' => false,
                'message' => 'Cannot read image dimensions',
                'details' => 'Unable to determine image size',
                'suggestion' => 'Ensure image file is valid and not corrupted'
            ];
        }
        
        $imageWidth = $imageDimensions[0];
        $imageHeight = $imageDimensions[1];
        
        // Check minimum dimensions (critical)
        if ($imageWidth < self::IMAGE_MIN_SIZE || $imageHeight < self::IMAGE_MIN_SIZE) {
            return [
                'check_name' => 'cover_image_validation',
                'passed' => false,
                'message' => 'Cover image too small',
                'details' => $imageWidth . 'x' . $imageHeight . 'px (minimum ' . self::IMAGE_MIN_SIZE . 'x' . self::IMAGE_MIN_SIZE . 'px required)',
                'suggestion' => 'Use an image at least 1400x1400 pixels'
            ];
        }
        
        // Check maximum dimensions (critical)
        if ($imageWidth > self::IMAGE_MAX_SIZE || $imageHeight > self::IMAGE_MAX_SIZE) {
            return [
                'check_name' => 'cover_image_validation',
                'passed' => false,
                'message' => 'Cover image too large',
                'details' => $imageWidth . 'x' . $imageHeight . 'px (maximum ' . self::IMAGE_MAX_SIZE . 'x' . self::IMAGE_MAX_SIZE . 'px)',
                'suggestion' => 'Resize image to 3000x3000 pixels or smaller'
            ];
        }
        
        return [
            'check_name' => 'cover_image_validation',
            'passed' => true,
            'message' => 'Cover image valid',
            'details' => $imageWidth . 'x' . $imageHeight . 'px, ' . $contentType,
            'suggestion' => '',
            'image_dimensions' => ['width' => $imageWidth, 'height' => $imageHeight]
        ];
    }
    
    /**
     * Check iTunes namespace presence (warning)
     */
    private function checkItunesNamespace($xmlToCheck)
    {
        $namespaces = $xmlToCheck->getNamespaces(true);
        $hasItunes = isset($namespaces['itunes']);
        
        return [
            'check_name' => 'itunes_namespace',
            'passed' => $hasItunes,
            'level' => 'warning',
            'message' => $hasItunes ? 'iTunes namespace present' : 'Missing iTunes namespace',
            'details' => $hasItunes ? 'Feed includes iTunes podcast tags' : 'Feed may not work properly in Apple Podcasts',
            'suggestion' => $hasItunes ? '' : 'Add xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" to <rss> tag'
        ];
    }
    
    /**
     * Check iTunes tags presence (warning)
     */
    private function checkItunesTags($xmlToCheck)
    {
        $namespaces = $xmlToCheck->getNamespaces(true);
        if (!isset($namespaces['itunes'])) {
            return [
                'check_name' => 'itunes_tags',
                'passed' => false,
                'level' => 'warning',
                'message' => 'iTunes tags not available',
                'details' => 'No iTunes namespace found',
                'suggestion' => ''
            ];
        }
        
        $itunesElements = $xmlToCheck->channel->children($namespaces['itunes']);
        $hasAuthor = !empty($itunesElements->author);
        $hasCategory = !empty($itunesElements->category);
        
        $hasRecommendedTags = ($hasAuthor && $hasCategory);
        
        return [
            'check_name' => 'itunes_tags',
            'passed' => $hasRecommendedTags,
            'level' => 'warning',
            'message' => $hasRecommendedTags ? 'iTunes tags present' : 'Missing recommended iTunes tags',
            'details' => $hasRecommendedTags ? 'itunes:author and itunes:category found' : 'Missing itunes:author or itunes:category',
            'suggestion' => $hasRecommendedTags ? '' : 'Add iTunes tags for better podcast directory compatibility'
        ];
    }
    
    /**
     * Check image size recommendation (warning)
     */
    private function checkImageSizeRecommendation($dimensionsToCheck)
    {
        $width = $dimensionsToCheck['width'];
        $height = $dimensionsToCheck['height'];
        
        $isRecommendedSize = ($width >= self::IMAGE_RECOMMENDED_MIN && $height >= self::IMAGE_RECOMMENDED_MIN);
        
        return [
            'check_name' => 'image_size_recommendation',
            'passed' => $isRecommendedSize,
            'level' => 'warning',
            'message' => $isRecommendedSize ? 'Image size optimal' : 'Image size below recommended',
            'details' => $isRecommendedSize ? $width . 'x' . $height . 'px meets recommendations' : $width . 'x' . $height . 'px (recommended: ' . self::IMAGE_RECOMMENDED_MIN . 'x' . self::IMAGE_RECOMMENDED_MIN . 'px or larger)',
            'suggestion' => $isRecommendedSize ? '' : 'For best quality, use 3000x3000px image'
        ];
    }
    
    /**
     * Check response time (warning)
     */
    private function checkResponseTime($timeInSeconds)
    {
        $isFastEnough = ($timeInSeconds < self::RESPONSE_TIME_WARNING);
        
        return [
            'check_name' => 'response_time',
            'passed' => $isFastEnough,
            'level' => 'warning',
            'message' => $isFastEnough ? 'Response time acceptable' : 'Slow response time',
            'details' => $isFastEnough ? round($timeInSeconds, 2) . "s" : round($timeInSeconds, 2) . "s (>" . self::RESPONSE_TIME_WARNING . "s)",
            'suggestion' => $isFastEnough ? '' : 'Feed may timeout during updates. Contact your podcast host.'
        ];
    }
    
    /**
     * Check item pubDate presence (warning)
     */
    private function checkItemPubDates($xmlToCheck)
    {
        $items = $xmlToCheck->channel->item ?? [];
        if (count($items) === 0) {
            return [
                'check_name' => 'item_pubdates',
                'passed' => true,
                'level' => 'warning',
                'message' => 'No items to check',
                'details' => '',
                'suggestion' => ''
            ];
        }
        
        $itemsWithPubDate = 0;
        foreach ($items as $item) {
            if (!empty($item->pubDate)) {
                $itemsWithPubDate++;
            }
        }
        
        $allHavePubDate = ($itemsWithPubDate === count($items));
        
        return [
            'check_name' => 'item_pubdates',
            'passed' => $allHavePubDate,
            'level' => 'warning',
            'message' => $allHavePubDate ? 'All items have pubDate' : 'Some items missing pubDate',
            'details' => $allHavePubDate ? 'All episodes have publication dates' : "{$itemsWithPubDate}/" . count($items) . " items have pubDate",
            'suggestion' => $allHavePubDate ? '' : 'Add <pubDate> to all <item> elements for proper sorting'
        ];
    }
    
    /**
     * Extract feed metadata for display
     */
    private function extractFeedMetadataForValidation($xmlToExtract, $imageUrl, $episodesCheck)
    {
        $channel = $xmlToExtract->channel;
        
        // Determine feed type
        $feedType = 'RSS 2.0';
        $namespaces = $xmlToExtract->getNamespaces(true);
        if (isset($namespaces['itunes'])) {
            $feedType = 'RSS 2.0 with iTunes';
        }
        
        // Get latest episode date
        $latestEpisodeDate = 'Unknown';
        $items = $channel->item ?? [];
        if (count($items) > 0 && !empty($items[0]->pubDate)) {
            $latestEpisodeDate = date('M j, Y', strtotime((string)$items[0]->pubDate));
        }
        
        return [
            'title' => (string)$channel->title,
            'description' => (string)$channel->description,
            'feed_type' => $feedType,
            'episode_count' => $episodesCheck['episode_count'] ?? 0,
            'latest_episode' => $latestEpisodeDate,
            'image_url' => $imageUrl,
            'language' => (string)($channel->language ?? 'Not specified')
        ];
    }
}
