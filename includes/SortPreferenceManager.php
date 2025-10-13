<?php
require_once __DIR__ . '/../config/config.php';

/**
 * SortPreferenceManager Class
 * Manages server-side sort preference storage
 * This controls how feed.php outputs the RSS feed for external app consumption
 */
class SortPreferenceManager
{
    private $preferenceFile;
    private $defaultPreference = [
        'sort' => 'episodes',
        'order' => 'desc',
        'last_updated' => null
    ];

    public function __construct()
    {
        $this->preferenceFile = DATA_DIR . '/sort-preference.json';
        $this->ensureFileExists();
    }

    /**
     * Ensure preference file exists with default values
     */
    private function ensureFileExists()
    {
        if (!file_exists($this->preferenceFile)) {
            $this->defaultPreference['last_updated'] = date('c');
            $this->savePreference($this->defaultPreference['sort'], $this->defaultPreference['order']);
        }
    }

    /**
     * Get current default sort preference
     * This is what feed.php uses to generate the RSS feed
     */
    public function getPreference(): array
    {
        try {
            if (!file_exists($this->preferenceFile)) {
                return $this->defaultPreference;
            }

            $content = file_get_contents($this->preferenceFile);
            if ($content === false) {
                error_log('SortPreferenceManager: Failed to read preference file');
                return $this->defaultPreference;
            }

            $data = json_decode($content, true);
            if ($data === null || !isset($data['sort']) || !isset($data['order'])) {
                error_log('SortPreferenceManager: Invalid preference file format');
                return $this->defaultPreference;
            }

            return [
                'sort' => $data['sort'],
                'order' => $data['order'],
                'last_updated' => $data['last_updated'] ?? null
            ];
        } catch (Exception $e) {
            error_log('SortPreferenceManager: Error reading preference - ' . $e->getMessage());
            return $this->defaultPreference;
        }
    }

    /**
     * Save sort preference - THIS CONTROLS THE FEED OUTPUT
     * When admin changes sort, this saves it and feed.php will use it
     */
    public function savePreference(string $sort, string $order): bool
    {
        try {
            // Validate sort parameter
            $allowedSorts = ['episodes', 'date', 'title', 'status'];
            if (!in_array($sort, $allowedSorts)) {
                error_log("SortPreferenceManager: Invalid sort value: $sort");
                return false;
            }

            // Validate order parameter
            $allowedOrders = ['asc', 'desc'];
            if (!in_array($order, $allowedOrders)) {
                error_log("SortPreferenceManager: Invalid order value: $order");
                return false;
            }

            $data = [
                'sort' => $sort,
                'order' => $order,
                'last_updated' => date('c')
            ];

            $json = json_encode($data, JSON_PRETTY_PRINT);
            if ($json === false) {
                error_log('SortPreferenceManager: Failed to encode JSON');
                return false;
            }

            // Ensure data directory is writable
            if (!is_writable(DATA_DIR)) {
                @chmod(DATA_DIR, 0777);
            }

            // Write to file
            $result = file_put_contents($this->preferenceFile, $json, LOCK_EX);
            if ($result === false) {
                error_log('SortPreferenceManager: Failed to write preference file');
                return false;
            }

            // Set file permissions
            @chmod($this->preferenceFile, 0666);

            return true;
        } catch (Exception $e) {
            error_log('SortPreferenceManager: Error saving preference - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Convert frontend sort key to backend parameters
     * Maps UI sort options to feed.php parameters
     */
    public function convertSortKey(string $sortKey): array
    {
        $sortMap = [
            'date-newest' => ['sort' => 'episodes', 'order' => 'desc'],
            'date-oldest' => ['sort' => 'episodes', 'order' => 'asc'],
            'title-az' => ['sort' => 'title', 'order' => 'asc'],
            'title-za' => ['sort' => 'title', 'order' => 'desc'],
            'status-active' => ['sort' => 'status', 'order' => 'desc'],
            'status-inactive' => ['sort' => 'status', 'order' => 'asc']
        ];

        return $sortMap[$sortKey] ?? ['sort' => 'episodes', 'order' => 'desc'];
    }

    /**
     * Get preference as frontend sort key
     * Converts backend parameters to UI sort key
     */
    public function getPreferenceAsSortKey(): string
    {
        $pref = $this->getPreference();
        
        $reverseMap = [
            'episodes_desc' => 'date-newest',
            'episodes_asc' => 'date-oldest',
            'title_asc' => 'title-az',
            'title_za' => 'title-za',
            'status_desc' => 'status-active',
            'status_asc' => 'status-inactive'
        ];

        $key = $pref['sort'] . '_' . $pref['order'];
        return $reverseMap[$key] ?? 'date-newest';
    }
}
