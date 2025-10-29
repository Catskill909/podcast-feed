<?php

/**
 * Menu XML Handler
 * Handles XML operations for menu configuration storage
 * Pattern follows AdsXMLHandler.php for consistency
 */

class MenuXMLHandler {
    private $xmlFile;
    private $uploadsDir;

    public function __construct() {
        $this->xmlFile = __DIR__ . '/../data/menu-config.xml';
        $this->uploadsDir = __DIR__ . '/../uploads/menu';
        $this->ensureXMLExists();
        $this->ensureUploadsDir();
    }

    /**
     * Get site branding configuration
     */
    public function getBranding() {
        $xml = simplexml_load_file($this->xmlFile);
        
        if (!isset($xml->branding)) {
            return $this->getDefaultBranding();
        }

        return [
            'site_title' => (string)$xml->branding->site_title,
            'logo_type' => (string)$xml->branding->logo_type,
            'logo_icon' => (string)$xml->branding->logo_icon,
            'logo_image' => (string)$xml->branding->logo_image
        ];
    }

    /**
     * Save site branding configuration
     */
    public function saveBranding($data) {
        $xml = simplexml_load_file($this->xmlFile);
        
        if (!isset($xml->branding)) {
            $xml->addChild('branding');
        }

        $xml->branding->site_title = $data['site_title'];
        $xml->branding->logo_type = $data['logo_type'];
        $xml->branding->logo_icon = $data['logo_icon'] ?? '';
        $xml->branding->logo_image = $data['logo_image'] ?? '';

        return $this->saveXML($xml);
    }

    /**
     * Get all menu items
     */
    public function getItems() {
        $xml = simplexml_load_file($this->xmlFile);
        $items = [];

        if (!isset($xml->items) || !isset($xml->items->item)) {
            return $items;
        }

        foreach ($xml->items->item as $item) {
            $items[] = [
                'id' => (string)$item->id,
                'label' => (string)$item->label,
                'url' => (string)$item->url,
                'icon_type' => (string)$item->icon_type,
                'icon_value' => (string)$item->icon_value,
                'target' => (string)$item->target,
                'order' => (int)$item->order,
                'active' => (int)$item->active
            ];
        }

        // Sort by order
        usort($items, function($a, $b) {
            return $a['order'] - $b['order'];
        });

        return $items;
    }

    /**
     * Get single menu item by ID
     */
    public function getItem($id) {
        $items = $this->getItems();
        foreach ($items as $item) {
            if ($item['id'] === $id) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Add new menu item
     */
    public function addItem($data) {
        $xml = simplexml_load_file($this->xmlFile);

        if (!isset($xml->items)) {
            $xml->addChild('items');
        }

        $item = $xml->items->addChild('item');
        $item->addChild('id', $data['id']);
        $item->addChild('label', htmlspecialchars($data['label']));
        $item->addChild('url', htmlspecialchars($data['url']));
        $item->addChild('icon_type', $data['icon_type']);
        $item->addChild('icon_value', htmlspecialchars($data['icon_value'] ?? ''));
        $item->addChild('target', $data['target']);
        $item->addChild('order', $data['order']);
        $item->addChild('active', $data['active'] ?? 1);

        return $this->saveXML($xml);
    }

    /**
     * Update existing menu item
     */
    public function updateItem($id, $data) {
        $xml = simplexml_load_file($this->xmlFile);

        if (!isset($xml->items) || !isset($xml->items->item)) {
            return false;
        }

        foreach ($xml->items->item as $item) {
            if ((string)$item->id === $id) {
                $item->label = htmlspecialchars($data['label']);
                $item->url = htmlspecialchars($data['url']);
                $item->icon_type = $data['icon_type'];
                $item->icon_value = htmlspecialchars($data['icon_value'] ?? '');
                $item->target = $data['target'];
                // Don't update order or active here - separate methods
                
                return $this->saveXML($xml);
            }
        }

        return false;
    }

    /**
     * Delete menu item
     */
    public function deleteItem($id) {
        $xml = simplexml_load_file($this->xmlFile);

        if (!isset($xml->items) || !isset($xml->items->item)) {
            return false;
        }

        $index = 0;
        foreach ($xml->items->item as $item) {
            if ((string)$item->id === $id) {
                // Delete associated icon image if exists
                if ((string)$item->icon_type === 'image' && !empty((string)$item->icon_value)) {
                    $imagePath = __DIR__ . '/../' . (string)$item->icon_value;
                    if (file_exists($imagePath)) {
                        @unlink($imagePath);
                    }
                }
                
                unset($xml->items->item[$index]);
                return $this->saveXML($xml);
            }
            $index++;
        }

        return false;
    }

    /**
     * Reorder menu items
     */
    public function reorderItems($order) {
        $xml = simplexml_load_file($this->xmlFile);

        if (!isset($xml->items) || !isset($xml->items->item)) {
            return false;
        }

        // $order is array of IDs in new order
        foreach ($xml->items->item as $item) {
            $id = (string)$item->id;
            $newOrder = array_search($id, $order);
            if ($newOrder !== false) {
                $item->order = $newOrder + 1; // 1-indexed
            }
        }

        return $this->saveXML($xml);
    }

    /**
     * Toggle menu item active state
     */
    public function toggleItem($id, $active) {
        $xml = simplexml_load_file($this->xmlFile);

        if (!isset($xml->items) || !isset($xml->items->item)) {
            return false;
        }

        foreach ($xml->items->item as $item) {
            if ((string)$item->id === $id) {
                $item->active = $active ? 1 : 0;
                return $this->saveXML($xml);
            }
        }

        return false;
    }

    /**
     * Get next available ID
     */
    public function getNextId() {
        $items = $this->getItems();
        if (empty($items)) {
            return '1';
        }

        $maxId = 0;
        foreach ($items as $item) {
            $id = (int)$item['id'];
            if ($id > $maxId) {
                $maxId = $id;
            }
        }

        return (string)($maxId + 1);
    }

    /**
     * Get next order number
     */
    public function getNextOrder() {
        $items = $this->getItems();
        if (empty($items)) {
            return 1;
        }

        $maxOrder = 0;
        foreach ($items as $item) {
            if ($item['order'] > $maxOrder) {
                $maxOrder = $item['order'];
            }
        }

        return $maxOrder + 1;
    }

    /**
     * Ensure XML file exists with default structure
     */
    private function ensureXMLExists() {
        if (!file_exists($this->xmlFile)) {
            $this->createDefaultXML();
        }
    }

    /**
     * Create default XML structure with current menu items
     */
    private function createDefaultXML() {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><menu></menu>');
        
        // Default branding
        $branding = $xml->addChild('branding');
        $branding->addChild('site_title', 'Podcast Browser');
        $branding->addChild('logo_type', 'icon');
        $branding->addChild('logo_icon', 'fa-podcast');
        $branding->addChild('logo_image', '');

        // Default menu items (current menu structure)
        $items = $xml->addChild('items');
        
        // Browse
        $item1 = $items->addChild('item');
        $item1->addChild('id', '1');
        $item1->addChild('label', 'Browse');
        $item1->addChild('url', 'index.php');
        $item1->addChild('icon_type', 'none');
        $item1->addChild('icon_value', '');
        $item1->addChild('target', '_self');
        $item1->addChild('order', '1');
        $item1->addChild('active', '1');

        // Admin
        $item2 = $items->addChild('item');
        $item2->addChild('id', '2');
        $item2->addChild('label', 'Admin');
        $item2->addChild('url', 'admin.php');
        $item2->addChild('icon_type', 'fa');
        $item2->addChild('icon_value', 'fa-lock');
        $item2->addChild('target', '_self');
        $item2->addChild('order', '2');
        $item2->addChild('active', '1');

        $this->saveXML($xml);
    }

    /**
     * Get default branding
     */
    private function getDefaultBranding() {
        return [
            'site_title' => 'Podcast Browser',
            'logo_type' => 'icon',
            'logo_icon' => 'fa-podcast',
            'logo_image' => ''
        ];
    }

    /**
     * Ensure uploads directory exists
     */
    private function ensureUploadsDir() {
        if (!is_dir($this->uploadsDir)) {
            mkdir($this->uploadsDir, 0755, true);
        }
    }

    /**
     * Save XML to file
     */
    private function saveXML($xml) {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        
        return $dom->save($this->xmlFile) !== false;
    }
}
