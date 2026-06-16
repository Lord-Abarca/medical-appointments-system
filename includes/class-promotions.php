<?php
/**
 * Promotions Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAS_Promotions {
    private $wpdb;
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'mas_promotions';
    }

    /**
     * Get all promotions
     */
    public function get_all_promotions($status = null) {
        $query = "SELECT * FROM {$this->table_name}";
        
        if ($status) {
            $query .= $this->wpdb->prepare(" WHERE status = %s", $status);
        }
        
        $query .= " ORDER BY created_at DESC";
        
        return $this->wpdb->get_results($query);
    }

    /**
     * Get active promotions
     */
    public function get_active_promotions() {
        $today = current_time('Y-m-d');
        
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE status = 'active' 
            AND (start_date IS NULL OR start_date <= %s)
            AND (end_date IS NULL OR end_date >= %s)
            ORDER BY blocks_quantity ASC",
            $today,
            $today
        );
        
        return $this->wpdb->get_results($query);
    }

    /**
     * Get promotion by ID
     */
    public function get_promotion($id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        ));
    }

    /**
     * Create new promotion
     */
    public function create_promotion($data) {
        // Calculate discount percentage if not provided
        if (!isset($data['discount_percentage']) || empty($data['discount_percentage'])) {
            // Assuming we need to know the regular price per block
            // For now, we'll calculate it based on average box price
            $avg_price = $this->get_average_box_price();
            $regular_total = $data['blocks_quantity'] * $avg_price;
            $data['discount_percentage'] = (($regular_total - $data['package_price']) / $regular_total) * 100;
        }

        $result = $this->wpdb->insert(
            $this->table_name,
            array(
                'name' => sanitize_text_field($data['name']),
                'description' => sanitize_textarea_field($data['description']),
                'blocks_quantity' => absint($data['blocks_quantity']),
                'package_price' => floatval($data['package_price']),
                'discount_percentage' => floatval($data['discount_percentage']),
                'status' => sanitize_text_field($data['status']),
                'start_date' => !empty($data['start_date']) ? $data['start_date'] : null,
                'end_date' => !empty($data['end_date']) ? $data['end_date'] : null,
            ),
            array('%s', '%s', '%d', '%f', '%f', '%s', '%s', '%s')
        );

        if ($result === false) {
            return new WP_Error('db_insert_error', 'No se pudo crear la promoción');
        }

        return $this->wpdb->insert_id;
    }

    /**
     * Update promotion
     */
    public function update_promotion($id, $data) {
        // Recalculate discount percentage if needed
        if (isset($data['blocks_quantity']) && isset($data['package_price'])) {
            $avg_price = $this->get_average_box_price();
            $regular_total = $data['blocks_quantity'] * $avg_price;
            $data['discount_percentage'] = (($regular_total - $data['package_price']) / $regular_total) * 100;
        }

        $result = $this->wpdb->update(
            $this->table_name,
            array(
                'name' => sanitize_text_field($data['name']),
                'description' => sanitize_textarea_field($data['description']),
                'blocks_quantity' => absint($data['blocks_quantity']),
                'package_price' => floatval($data['package_price']),
                'discount_percentage' => floatval($data['discount_percentage']),
                'status' => sanitize_text_field($data['status']),
                'start_date' => !empty($data['start_date']) ? $data['start_date'] : null,
                'end_date' => !empty($data['end_date']) ? $data['end_date'] : null,
            ),
            array('id' => $id),
            array('%s', '%s', '%d', '%f', '%f', '%s', '%s', '%s'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('db_update_error', 'No se pudo actualizar la promoción');
        }

        return true;
    }

    /**
     * Delete promotion
     */
    public function delete_promotion($id) {
        $result = $this->wpdb->delete(
            $this->table_name,
            array('id' => $id),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Get average box price per hour
     */
    private function get_average_box_price() {
        $boxes_table = $this->wpdb->prefix . 'mas_boxes';
        $avg = $this->wpdb->get_var("SELECT AVG(price_per_hour) FROM {$boxes_table}");
        return $avg ? floatval($avg) : 10000; // Default fallback
    }

    /**
     * Apply promotion to rental
     */
    public function apply_promotion_to_rental($rental_id, $promotion_id, $blocks_used, $discount_applied) {
        $rental_promotions_table = $this->wpdb->prefix . 'mas_rental_promotions';
        
        return $this->wpdb->insert(
            $rental_promotions_table,
            array(
                'rental_id' => $rental_id,
                'promotion_id' => $promotion_id,
                'blocks_used' => $blocks_used,
                'discount_applied' => $discount_applied,
            ),
            array('%d', '%d', '%d', '%f')
        );
    }

    /**
     * Get promotion usage for a rental
     */
    public function get_rental_promotion($rental_id) {
        $rental_promotions_table = $this->wpdb->prefix . 'mas_rental_promotions';
        
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT rp.*, p.name as promotion_name, p.blocks_quantity 
            FROM {$rental_promotions_table} rp
            LEFT JOIN {$this->table_name} p ON rp.promotion_id = p.id
            WHERE rp.rental_id = %d",
            $rental_id
        ));
    }
}
