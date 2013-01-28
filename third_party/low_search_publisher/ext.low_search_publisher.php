<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine - by EllisLab
 *
 * @package     ExpressionEngine
 * @author      ExpressionEngine Dev Team
 * @copyright   Copyright (c) 2003 - 2011, EllisLab, Inc.
 * @license     http://expressionengine.com/user_guide/license.html
 * @link        http://expressionengine.com
 * @since       Version 2.0
 * @filesource
 */
 
// ------------------------------------------------------------------------

/**
 * Low Search Publisher Support Extension
 *
 * @package     ExpressionEngine
 * @subpackage  Addons
 * @category    Extension
 * @author      Brian Litzinger
 * @link        http://boldminded.com
 */

class Low_search_publisher_ext {
    
    public $settings        = array();
    public $description     = 'Low Search support for Publisher';
    public $docs_url        = '';
    public $name            = 'Low Search Publisher Support';
    public $settings_exist  = 'n';
    public $version         = '1.0';
    
    private $EE;
    
    /**
     * Constructor
     *
     * @param   mixed   Settings array or empty string if none exist.
     */
    public function __construct($settings = '')
    {
        $this->EE =& get_instance();
        $this->table = 'low_search_indexes';
    }

    // ----------------------------------------------------------------------
    
    public function low_search_data_query()
    {
        return array(
            'publisher_lang_id' => $this->EE->publisher_lib->lang_id,
            'publisher_status'  => $this->EE->publisher_lib->status
        );
    }

    public function low_search_insert_data()
    {
        // This isn't set yet when indexing via the ajax method, so just force to Open
        $status = isset($this->EE->publisher_lib->save_status) ? $this->EE->publisher_lib->save_status : PUBLISHER_STATUS_OPEN;

        return array(
            'publisher_lang_id' => $this->EE->publisher_lib->lang_id,
            'publisher_status'  => $status
        ); 
    }

    public function low_search_pre_search($params)
    {
        return array_merge($params, array(
            'publisher_lang_id' => $this->EE->publisher_lib->lang_id,
            'publisher_status'  => $this->EE->publisher_lib->status
        ));
    }

    public function low_search_query_result($results, $row, $field_id, $field_value)
    {
        // If we are in production mode, then the translated data is already in the
        // $results array that Low Search is parsing, so bail.
        if (PUBLISHER_MODE == 'production')
        {
            return $field_value;
        }
        else
        {   
            // Cache the get_all query first, it gets... ALL THE THINGS!
            if ( ! isset($this->EE->session->cache['low_search_publisher_results']))
            {
                $entry_ids = array();

                foreach ($results as $entry)
                {
                    $entry_ids[] = $entry['entry_id'];
                }

                $this->EE->session->cache['low_search_publisher_results'] = $this->EE->publisher_entry->get_all($entry_ids, $results);
            }

            $cache = $this->EE->session->cache['low_search_publisher_results'];

            $entry_id = $row['entry_id'];

            // Loop through our cached/translated data and grab the translated version of the field instead
            foreach ($cache as $cache_row)
            {
                if ($cache_row['entry_id'] == $entry_id AND isset($cache_row['field_id_'.$field_id]))
                {
                    return $cache_row['field_id_'.$field_id];
                } 
            }
        }

        return $field_value;
    }

    /**
     * Activate Extension
     *
     * This function enters the extension into the exp_extensions table
     *
     * @see http://codeigniter.com/user_guide/database/index.html for
     * more information on the db class.
     *
     * @return void
     */
    public function activate_extension()
    {
        // Setup custom settings in this array.
        $this->settings = array();
        
        // Add new hooks
        $ext_template = array(
            'class'    => __CLASS__,
            'settings' => serialize($this->settings),
            'priority' => 5,
            'version'  => $this->version,
            'enabled'  => 'y'
        );

        $extensions = array(
            array('hook'=>'low_search_data_query', 'method'=>'low_search_data_query'),
            array('hook'=>'low_search_insert_data', 'method'=>'low_search_insert_data'),
            array('hook'=>'low_search_pre_search', 'method'=>'low_search_pre_search'),
            array('hook'=>'low_search_query_result', 'method'=>'low_search_query_result')
        );

        foreach($extensions as $extension)
        {
            $this->EE->db->insert('extensions', array_merge($ext_template, $extension));
        }       

        $this->EE->load->dbforge();

        if ($this->EE->db->table_exists($this->table) AND ! $this->EE->db->field_exists('publisher_lang_id', $this->table)) 
        {
            $this->EE->db->query("ALTER TABLE `{$this->EE->db->dbprefix}{$this->table}` ADD COLUMN `publisher_lang_id` int(4) NOT NULL DEFAULT {$this->EE->publisher_lib->default_lang_id} AFTER `site_id`");
            $this->EE->db->query("ALTER TABLE `{$this->EE->db->dbprefix}{$this->table}` ADD COLUMN `publisher_status` varchar(24) NULL DEFAULT '". PUBLISHER_STATUS_OPEN ."' AFTER `publisher_lang_id`");

            $this->EE->db->query("ALTER TABLE `{$this->EE->db->dbprefix}{$this->table}` DROP PRIMARY KEY");
            $this->EE->db->query("ALTER TABLE `{$this->EE->db->dbprefix}{$this->table}` ADD PRIMARY KEY (collection_id, entry_id, publisher_lang_id, publisher_status)");
        }
    }   

    // ----------------------------------------------------------------------
    
    /**
     * Disable Extension
     *
     * This method removes information from the exp_extensions table
     *
     * @return void
     */
    function disable_extension()
    {
        $this->EE->db->where('class', __CLASS__);
        $this->EE->db->delete('extensions');

        if ($this->EE->db->table_exists($this->table) AND $this->EE->db->field_exists('publisher_lang_id', $this->table)) 
        {
            $this->EE->dbforge->drop_column($this->table, 'publisher_status');
            $this->EE->dbforge->drop_column($this->table, 'publisher_lang_id');
        }
    }

    // ----------------------------------------------------------------------

    /**
     * Update Extension
     *
     * This function performs any necessary db updates when the extension
     * page is visited
     *
     * @return  mixed   void on update / false if none
     */
    function update_extension($current = '')
    {
        if ($current == '' OR $current == $this->version)
        {
            return FALSE;
        }
    }   
    
    // ----------------------------------------------------------------------
}

/* End of file ext.navee_publisher.php */
/* Location: /system/expressionengine/third_party/navee_publisher/ext.navee_publisher.php */