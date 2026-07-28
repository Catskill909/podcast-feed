<?php

/**
 * Menu Manager
 * Business logic for menu configuration management
 * Pattern follows AdsManager.php for consistency
 */

require_once __DIR__ . '/MenuXMLHandler.php';
require_once __DIR__ . '/../config/config.php';

class MenuManager
{
    private MenuXMLHandler $xmlHandler;
    private string $uploadsDir;
    private string $uploadsUrl;

    public function __construct()
    {
        $this->xmlHandler = new MenuXMLHandler();
        $this->uploadsDir = __DIR__ . '/../uploads/menu';
        $this->uploadsUrl = APP_URL . '/uploads/menu';
    }

    /**
     * Get site branding configuration
     */
    public function getBranding()
    {
        return $this->xmlHandler->getBranding();
    }

    /**
     * Save site branding configuration
     */
    public function saveBranding(array $data, ?array $logoFile = null)
    {
        // Validate required fields
        if (empty($data['site_title'])) {
            return [
                'success' => false,
                'message' => 'Site title is required'
            ];
        }

        // Handle logo upload if provided
        if ($logoFile && $logoFile['error'] === UPLOAD_ERR_OK) {
            $uploadResult = $this->uploadLogoImage($logoFile);
            if (!$uploadResult['success']) {
                return $uploadResult;
            }
            $data['logo_image'] = $uploadResult['path'];
            // Only set logo_type to 'image' if it's not already set to 'image' or 'image_only'
            if ($data['logo_type'] !== 'image' && $data['logo_type'] !== 'image_only') {
                $data['logo_type'] = 'image';
            }
        }

        // Save to XML
        $success = $this->xmlHandler->saveBranding($data);

        return [
            'success' => $success,
            'message' => $success ? 'Branding saved successfully' : 'Failed to save branding'
        ];
    }

    /**
     * Get all menu items
     */
    public function getMenuItems(bool $activeOnly = false)
    {
        $items = $this->xmlHandler->getItems();

        if ($activeOnly) {
            $items = array_filter($items, function (array $item) {
                return $item['active'] == 1;
            });
            // Reset array keys after filtering
            $items = array_values($items);
        }

        return $items;
    }

    /**
     * Get single menu item
     */
    public function getMenuItem(string $id)
    {
        return $this->xmlHandler->getItem($id);
    }

    /**
     * Add new menu item
     */
    public function addMenuItem(array $data, ?array $iconFile = null)
    {
        // Validate
        $validation = $this->validateMenuItem($data);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['message']
            ];
        }

        // Handle icon upload if provided
        if ($iconFile && $iconFile['error'] === UPLOAD_ERR_OK) {
            $uploadResult = $this->uploadIconImage($iconFile);
            if (!$uploadResult['success']) {
                return $uploadResult;
            }
            $data['icon_value'] = $uploadResult['path'];
            $data['icon_type'] = 'image';
        }

        // Generate ID and order
        $data['id'] = $this->xmlHandler->getNextId();
        $data['order'] = $this->xmlHandler->getNextOrder();
        $data['active'] = 1;

        // Add to XML
        $success = $this->xmlHandler->addItem($data);

        return [
            'success' => $success,
            'message' => $success ? 'Menu item added successfully' : 'Failed to add menu item',
            'id' => $data['id']
        ];
    }

    /**
     * Update existing menu item
     */
    public function updateMenuItem(string $id, array $data, ?array $iconFile = null)
    {
        // Validate
        $validation = $this->validateMenuItem($data);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['message']
            ];
        }

        // Handle icon upload if provided
        if ($iconFile && $iconFile['error'] === UPLOAD_ERR_OK) {
            // Delete old icon if exists
            $oldItem = $this->xmlHandler->getItem($id);
            if ($oldItem && $oldItem['icon_type'] === 'image' && !empty($oldItem['icon_value'])) {
                $oldPath = __DIR__ . '/../' . $oldItem['icon_value'];
                if (file_exists($oldPath)) {
                    @unlink($oldPath);
                }
            }

            $uploadResult = $this->uploadIconImage($iconFile);
            if (!$uploadResult['success']) {
                return $uploadResult;
            }
            $data['icon_value'] = $uploadResult['path'];
            $data['icon_type'] = 'image';
        }

        // Update in XML
        $success = $this->xmlHandler->updateItem($id, $data);

        return [
            'success' => $success,
            'message' => $success ? 'Menu item updated successfully' : 'Failed to update menu item'
        ];
    }

    /**
     * Delete menu item
     */
    public function deleteMenuItem(string $id)
    {
        $success = $this->xmlHandler->deleteItem($id);

        return [
            'success' => $success,
            'message' => $success ? 'Menu item deleted successfully' : 'Failed to delete menu item'
        ];
    }

    /**
     * Reorder menu items
     */
    public function reorderMenuItems(array $order)
    {
        $success = $this->xmlHandler->reorderItems($order);

        return [
            'success' => $success,
            'message' => $success ? 'Menu items reordered successfully' : 'Failed to reorder menu items'
        ];
    }

    /**
     * Toggle menu item active state
     */
    public function toggleMenuItem(string $id, bool $active)
    {
        $success = $this->xmlHandler->toggleItem($id, $active);

        return [
            'success' => $success,
            'message' => $success ? 'Menu item toggled successfully' : 'Failed to toggle menu item'
        ];
    }

    /**
     * Validate menu item data
     */
    public function validateMenuItem(array $data)
    {
        // Required fields
        if (empty($data['label'])) {
            return [
                'valid' => false,
                'message' => 'Menu label is required'
            ];
        }

        if (empty($data['url'])) {
            return [
                'valid' => false,
                'message' => 'Menu URL is required'
            ];
        }

        // Validate URL format
        if (!$this->isValidUrl($data['url'])) {
            return [
                'valid' => false,
                'message' => 'Invalid URL format. Use relative URLs (e.g., /about.php) or full URLs'
            ];
        }

        // Validate icon type
        $validIconTypes = ['none', 'fa', 'image'];
        if (!in_array($data['icon_type'], $validIconTypes)) {
            return [
                'valid' => false,
                'message' => 'Invalid icon type'
            ];
        }

        // Validate Font Awesome icon if specified
        if ($data['icon_type'] === 'fa' && !empty($data['icon_value'])) {
            if (!$this->isValidFontAwesomeIcon($data['icon_value'])) {
                return [
                    'valid' => false,
                    'message' => 'Invalid Font Awesome icon format. Use format: fa-icon-name'
                ];
            }
        }

        // Validate target
        $validTargets = ['_self', '_blank'];
        if (!in_array($data['target'], $validTargets)) {
            return [
                'valid' => false,
                'message' => 'Invalid target value'
            ];
        }

        return [
            'valid' => true,
            'message' => 'Valid'
        ];
    }

    /**
     * Validate URL format
     */
    private function isValidUrl(string $url)
    {
        // Allow relative URLs
        if (strpos($url, '/') === 0 || strpos($url, './') === 0) {
            return true;
        }

        // Allow simple filenames
        if (preg_match('/^[a-zA-Z0-9_-]+\.php$/', $url)) {
            return true;
        }

        // Allow full URLs with protocol
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return true;
        }

        // Allow domain names without protocol (e.g., example.com, subdomain.example.com)
        if (preg_match('/^[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?(\.[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?)+$/', $url)) {
            return true;
        }

        // Allow anchor links
        if (strpos($url, '#') === 0) {
            return true;
        }

        return false;
    }

    /**
     * Validate Font Awesome icon format
     */
    private function isValidFontAwesomeIcon(string $icon)
    {
        // Must start with fa- and contain only lowercase letters, numbers, and hyphens
        return preg_match('/^fa-[a-z0-9-]+$/', $icon) === 1;
    }

    /**
     * Upload logo image
     */
    private function uploadLogoImage(array $file)
    {
        return $this->uploadImage($file, 'logo');
    }

    /**
     * Upload icon image
     */
    private function uploadIconImage(array $file)
    {
        return $this->uploadImage($file, 'icon');
    }

    /**
     * Upload image (logo or icon)
     */
    private function uploadImage(array $file, string $type = 'logo')
    {
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);

        if (!in_array($mimeType, $allowedTypes)) {
            return [
                'success' => false,
                'message' => 'Invalid file type. Only JPG, PNG, GIF, and SVG are allowed'
            ];
        }

        // Validate file size (max 2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            return [
                'success' => false,
                'message' => 'File size exceeds 2MB limit'
            ];
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $type . '_' . uniqid() . '.' . $extension;
        $filepath = $this->uploadsDir . '/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return [
                'success' => false,
                'message' => 'Failed to upload file'
            ];
        }

        return [
            'success' => true,
            'path' => $this->uploadsUrl . '/' . $filename,
            'filename' => $filename
        ];
    }
}
