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

class Publisher_low_search_ext {
    
    public $settings        = array();
    public $description     = 'Adds Low Search support to Publisher';
    public $docs_url        = '';
    public $name            = 'Publisher - Low Search Support';
    public $settings_exist  = 'n';
    public $version         = '1.0';

    private $table          = 'low_search_indexes';
    private $EE;
    
    /**
     * Constructor
     *
     * @param   mixed   Settings array or empty string if none exist.
     */
    public function __construct($settings = '')
    {
        $this->EE =& get_instance();

        // Create cache
        if (! isset($this->EE->session->cache['publisher_low_search']))
        {
            $this->EE->session->cache['publisher_low_search'] = array();
        }
        $this->cache =& $this->EE->session->cache['publisher_low_search'];
    }

    public function low_search_update_index($params, $entry = array())
    {
        // If batch indexing, just take the params from the entry row
        if (isset($this->cache['batch_indexing'])) 
        {
            $params['publisher_lang_id'] = $entry['publisher_lang_id'];
            $params['publisher_status'] = $entry['publisher_status'];
        }
        // Otherise take it from the current data
        else
        {
            // This isn't set yet when indexing via the ajax method, so just force to Open
            $status = isset($this->EE->publisher_lib->save_status) ? $this->EE->publisher_lib->save_status : PUBLISHER_STATUS_OPEN;
            
            $params['publisher_lang_id'] = $this->EE->publisher_lib->lang_id;
            $params['publisher_status']  = $this->EE->publisher_lib->status;
        }

        return $params;
    }

    public function low_search_pre_search($params)
    {
        $params['add_to_query'] = array(
            'publisher_lang_id' => $this->EE->publisher_lib->lang_id,
            'publisher_status'  => $this->EE->publisher_lib->status
        );

        return $params;
    }

    public function low_search_build_index($fields, $channel_ids, $entry_ids, $start, $batch_size)
    {
        $this->cache['batch_indexing'] = TRUE;

        // --------------------------------------
        // Build query
        // --------------------------------------
        $fields[] = 't.publisher_lang_id';
        $fields[] = 't.publisher_status';

        $this->EE->db->select($fields)
                     ->from('publisher_titles t')
                     ->join('publisher_data d', 't.entry_id = d.entry_id', 'inner')
                     ->where_in('t.channel_id', low_flatten_results($channel_ids, 'channel_id'));

        // --------------------------------------
        // Limit to given entries
        // --------------------------------------

        if ($entry_ids)
        {
            $this->EE->db->where_in('t.entry_id', $entry_ids);
        }

        // --------------------------------------
        // Limit entries by batch size, if given
        // --------------------------------------

        if ($start !== FALSE && is_numeric($start))
        {
            $this->EE->db->limit($ext['batch_size'], $start);
        }

        // --------------------------------------
        // Order it, just in case
        // --------------------------------------

        $this->EE->db->order_by('entry_id', 'asc');

        // --------------------------------------
        // Get it
        // --------------------------------------

        $query = $this->EE->db->get();

        return $query;
    }

    public function low_search_excerpt($entry_ids, $row, $eid)
    {
        // If its not the default language, get the translated value of the field to update
        // the excerpt string.
        if ($this->EE->publisher_lib->lang_id != $this->EE->publisher_lib->default_lang_id)
        {
            $excerpt = $this->EE->publisher_model->get_field_value(
                $row['entry_id'], 
                'field_id_'.$eid, 
                $this->EE->publisher_lib->status, 
                $this->EE->publisher_lib->lang_id
            );

            return array($excerpt, FALSE);
        }

        return FALSE;
    }

    /**
     * Activate Extension
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
            array('hook'=>'low_search_update_index', 'method'=>'low_search_update_index'),
            array('hook'=>'low_search_build_index', 'method'=>'low_search_build_index'),
            array('hook'=>'low_search_pre_search', 'method'=>'low_search_pre_search'),
            array('hook'=>'low_search_excerpt', 'method'=>'low_search_excerpt')
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

        $this->EE->db->where('publisher_status !=', PUBLISHER_STATUS_OPEN)
                     ->where('publisher_lang_id !=', $this->EE->publisher_lib->default_lang_id)
                     ->delete('low_search_indexes');

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