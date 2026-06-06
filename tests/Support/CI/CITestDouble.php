<?php

/**
 * Lightweight stand-in for the CodeIgniter super-object.
 *
 * CI3 models access their dependencies through CI_Model::__get(), which
 * calls the global get_instance() function defined in our bootstrap.  That
 * function returns this singleton so that model code like `$this->db->…`
 * transparently hits the mock instead of a real framework object.
 *
 * Usage in tests
 * --------------
 *   // Get the shared instance
 *   $ci = CITestDouble::instance();
 *
 *   // Swap the database mock with a pre-seeded one
 *   $ci->db->setRows([['invoice_id' => 1, 'invoice_status_id' => 2]]);
 *
 *   // Reset everything between tests
 *   CITestDouble::reset();
 */
#[AllowDynamicProperties]
class CITestDouble
{
    private static ?self $instance = null;

    // Typed properties for IDE assistance; the rest land on __set via AllowDynamicProperties
    public MockDB $db;

    public MockSession $session;

    public MockLoader $load;

    // Untyped so MockLoader can overwrite with a real Mdl_Settings instance when a test
    // calls $this->CI->load->model('settings/mdl_settings').
    public mixed $mdl_settings;

    private function __construct()
    {
        $this->db           = new MockDB();
        $this->session      = new MockSession();
        $this->load         = new MockLoader($this);
        $this->mdl_settings = new MockSettings();
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Returns a reference to the static singleton property so that
     * the global get_instance() function can satisfy CI3's requirement
     * of returning a reference without triggering a PHP notice.
     */
    public static function &ref(): ?self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /** Re-create the singleton and restore all mock defaults — call in setUp() for isolation. */
    public static function reset(): void
    {
        MockSettings::reset();
        self::$instance = new self();
    }
}
