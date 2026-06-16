<?php
/**
 * Clase para gestionar arriendos mensuales
 */

if (!defined('ABSPATH')) {
    exit;
}

class MAS_Monthly_Rentals {
    
    private static $table_name;
    private static $rentals_table;
    
    public static function init() {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'mas_monthly_rentals';
        self::$rentals_table = $wpdb->prefix . 'mas_box_rentals';
    }
    
    /**
     * Obtener todos los arriendos mensuales
     */
    public static function get_all($args = array()) {
        global $wpdb;
        self::init();
        
        $defaults = array(
            'status' => '',
            'box_id' => '',
            'professional_id' => '',
            'month' => '',
            'year' => '',
            'limit' => 50,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $values = array();
        
        if (!empty($args['status'])) {
            $where[] = 'mr.status = %s';
            $values[] = $args['status'];
        }
        
        if (!empty($args['box_id'])) {
            $where[] = 'mr.box_id = %d';
            $values[] = $args['box_id'];
        }
        
        if (!empty($args['professional_id'])) {
            $where[] = 'mr.professional_id = %d';
            $values[] = $args['professional_id'];
        }
        
        if (!empty($args['month'])) {
            $where[] = 'mr.month = %d';
            $values[] = $args['month'];
        }
        
        if (!empty($args['year'])) {
            $where[] = 'mr.year = %d';
            $values[] = $args['year'];
        }
        
        $where_sql = implode(' AND ', $where);
        
        $sql = "SELECT mr.*, 
                       b.box_name as box_name, 
                       b.box_number,
                       u.display_name as professional_name,
                       u.user_email as professional_email
                FROM " . self::$table_name . " mr
                LEFT JOIN {$wpdb->prefix}mas_boxes b ON mr.box_id = b.id
                LEFT JOIN {$wpdb->prefix}mas_professionals p ON mr.professional_id = p.id
                LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
                WHERE {$where_sql}
                ORDER BY mr.year DESC, mr.month DESC, mr.created_at DESC
                LIMIT %d OFFSET %d";
        
        $values[] = $args['limit'];
        $values[] = $args['offset'];
        
        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Contar total de arriendos mensuales
     */
    public static function count($args = array()) {
        global $wpdb;
        self::init();
        
        $where = array('1=1');
        $values = array();
        
        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }
        
        if (!empty($args['box_id'])) {
            $where[] = 'box_id = %d';
            $values[] = $args['box_id'];
        }
        
        if (!empty($args['professional_id'])) {
            $where[] = 'professional_id = %d';
            $values[] = $args['professional_id'];
        }
        
        $where_sql = implode(' AND ', $where);
        
        $sql = "SELECT COUNT(*) FROM " . self::$table_name . " WHERE {$where_sql}";
        
        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }
        
        return (int) $wpdb->get_var($sql);
    }
    
    /**
     * Obtener un arriendo mensual por ID
     */
    public static function get($id) {
        global $wpdb;
        self::init();
        
        $sql = $wpdb->prepare(
            "SELECT mr.*, 
                    b.box_name as box_name, 
                    b.box_number,
                    u.display_name as professional_name,
                    u.user_email as professional_email
             FROM " . self::$table_name . " mr
             LEFT JOIN {$wpdb->prefix}mas_boxes b ON mr.box_id = b.id
             LEFT JOIN {$wpdb->prefix}mas_professionals p ON mr.professional_id = p.id
             LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
             WHERE mr.id = %d",
            $id
        );
        
        return $wpdb->get_row($sql);
    }
    
    /**
     * Crear un arriendo mensual y generar registros individuales
     */
    public static function create($data) {
        global $wpdb;
        self::init();
        
        // Validar datos requeridos
        $required = array('box_id', 'professional_id', 'month', 'year', 'weekdays', 'start_time', 'end_time');
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return new WP_Error('missing_field', "Campo requerido: {$field}");
            }
        }
        
        // Convertir weekdays a JSON si es array
        $weekdays = is_array($data['weekdays']) ? json_encode($data['weekdays']) : $data['weekdays'];
        
        // Verificar conflictos antes de crear
        $conflicts = self::check_conflicts(
            $data['box_id'],
            $data['month'],
            $data['year'],
            json_decode($weekdays, true),
            $data['start_time'],
            $data['end_time']
        );
        
        if (!empty($conflicts)) {
            return new WP_Error('conflicts', 'Hay conflictos con arriendos existentes', $conflicts);
        }
        
        // Calcular precio mensual si no se especifica o es 0
        $monthly_price = isset($data['monthly_price']) ? floatval($data['monthly_price']) : 0;
        
        if ($monthly_price <= 0) {
            $monthly_price = self::calculate_monthly_price(
                $data['box_id'],
                $data['month'],
                $data['year'],
                json_decode($weekdays, true),
                $data['start_time'],
                $data['end_time']
            );
        }
        
        // Insertar arriendo mensual
        $status = isset($data['status']) ? $data['status'] : 'active';
        $payment_status = isset($data['payment_status']) ? $data['payment_status'] : 'pending';
        $payment_method = isset($data['payment_method']) ? $data['payment_method'] : '';
        
        $result = $wpdb->insert(
            self::$table_name,
            array(
                'box_id' => $data['box_id'],
                'professional_id' => $data['professional_id'],
                'month' => $data['month'],
                'year' => $data['year'],
                'weekdays' => $weekdays,
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'monthly_price' => $monthly_price,
                'status' => $status,
                'payment_status' => $payment_status,
                'payment_method' => $payment_method,
                'notes' => isset($data['notes']) ? $data['notes'] : ''
            ),
            array('%d', '%d', '%d', '%d', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Error al crear el arriendo mensual');
        }
        
        $monthly_rental_id = $wpdb->insert_id;
        
        // Generar registros individuales
        $individual_count = self::generate_individual_rentals($monthly_rental_id, $data);
        
        if (is_wp_error($individual_count)) {
            // Rollback: eliminar el arriendo mensual
            $wpdb->delete(self::$table_name, array('id' => $monthly_rental_id), array('%d'));
            return $individual_count;
        }
        
        return array(
            'monthly_rental_id' => $monthly_rental_id,
            'individual_rentals' => $individual_count
        );
    }
    
    /**
     * Generar registros individuales en wpjo_mas_box_rentals
     */
    private static function generate_individual_rentals($monthly_rental_id, $data) {
        global $wpdb;
        
        $weekdays = is_array($data['weekdays']) ? $data['weekdays'] : json_decode($data['weekdays'], true);
        $month = intval($data['month']);
        $year = intval($data['year']);
        
        // Obtener la duración del bloque de arriendos
        $block_duration = get_option('mas_box_rental_block_duration', 1); // en horas
        
        // Calcular todos los días del mes que coinciden con los weekdays seleccionados
        $first_day = "{$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
        $last_day = date('Y-m-t', strtotime($first_day));
        
        $current = new DateTime($first_day);
        $end = new DateTime($last_day);
        $end->modify('+1 day');
        
        $count = 0;
        $reference_id = 'MRENT' . $monthly_rental_id;
        
        while ($current < $end) {
            // PHP: 1=Lunes, 7=Domingo (formato ISO)
            $day_of_week = (int) $current->format('N');
            
            if (in_array($day_of_week, $weekdays)) {
                $rental_date = $current->format('Y-m-d');
                
                // Generar slots horarios para este día
                $start = new DateTime($rental_date . ' ' . $data['start_time']);
                $end_time = new DateTime($rental_date . ' ' . $data['end_time']);
                
                while ($start < $end_time) {
                    $slot_start = $start->format('H:i:s');
                    $start->modify("+{$block_duration} hour");
                    $slot_end = $start->format('H:i:s');
                    
                    // Insertar registro individual con estado y pago del arriendo mensual
                    $status = isset($data['status']) ? $data['status'] : 'active';
                    $payment_status = isset($data['payment_status']) ? $data['payment_status'] : 'pending';
                    $payment_method = isset($data['payment_method']) ? $data['payment_method'] : '';
                    
                    $result = $wpdb->insert(
                        self::$rentals_table,
                        array(
                            'box_id' => $data['box_id'],
                            'professional_id' => $data['professional_id'],
                            'rental_date' => $rental_date,
                            'start_time' => $slot_start,
                            'end_time' => $slot_end,
                            'status' => $status,
                            'payment_status' => $payment_status,
                            'payment_method' => $payment_method,
                            'reference_id' => $reference_id,
                            'monthly_rental_id' => $monthly_rental_id,
                            'notes' => 'Arriendo mensual automático',
                            'created_at' => current_time('mysql')
                        ),
                        array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
                    );
                    
                    if ($result) {
                        $count++;
                    }
                }
            }
            
            $current->modify('+1 day');
        }
        
        return $count;
    }
    
    /**
     * Verificar conflictos con arriendos existentes
     */
    public static function check_conflicts($box_id, $month, $year, $weekdays, $start_time, $end_time, $exclude_monthly_id = 0) {
        global $wpdb;
        self::init();
        
        $conflicts = array();
        
        // Calcular todos los días del mes que coinciden con los weekdays
        $first_day = "{$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
        $last_day = date('Y-m-t', strtotime($first_day));
        
        $current = new DateTime($first_day);
        $end = new DateTime($last_day);
        $end->modify('+1 day');
        
        while ($current < $end) {
            $day_of_week = (int) $current->format('N');
            
            if (in_array($day_of_week, $weekdays)) {
                $rental_date = $current->format('Y-m-d');
                
                // Buscar arriendos existentes que se superpongan
                $sql = $wpdb->prepare(
                    "SELECT id, start_time, end_time, status 
                     FROM " . self::$rentals_table . "
                     WHERE box_id = %d 
                     AND rental_date = %s
                     AND status IN ('pending', 'active', 'completed', 'interno')
                     AND (
                         (start_time < %s AND end_time > %s) OR
                         (start_time >= %s AND start_time < %s) OR
                         (end_time > %s AND end_time <= %s)
                     )",
                    $box_id,
                    $rental_date,
                    $end_time, $start_time,
                    $start_time, $end_time,
                    $start_time, $end_time
                );
                
                // Excluir arriendos del mismo monthly_rental si estamos editando
                if ($exclude_monthly_id > 0) {
                    $sql .= $wpdb->prepare(" AND (monthly_rental_id IS NULL OR monthly_rental_id != %d)", $exclude_monthly_id);
                }
                
                $existing = $wpdb->get_results($sql);
                
                if (!empty($existing)) {
                    $conflicts[] = array(
                        'date' => $rental_date,
                        'conflicts' => $existing
                    );
                }
            }
            
            $current->modify('+1 day');
        }
        
        return $conflicts;
    }
    
    /**
     * Cancelar un arriendo mensual y sus registros individuales
     */
    public static function cancel($id) {
        global $wpdb;
        self::init();
        
        // Actualizar estado del arriendo mensual
        $result = $wpdb->update(
            self::$table_name,
            array('status' => 'cancelled'),
            array('id' => $id),
            array('%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Error al cancelar el arriendo mensual');
        }
        
        // Cancelar todos los registros individuales futuros
        $today = current_time('Y-m-d');
        $cancelled_count = $wpdb->update(
            self::$rentals_table,
            array('status' => 'cancelled'),
            array(
                'monthly_rental_id' => $id
            ),
            array('%s'),
            array('%d')
        );
        
        return array(
            'monthly_rental_id' => $id,
            'cancelled_individual_rentals' => $cancelled_count
        );
    }
    
    /**
     * Eliminar un arriendo mensual y sus registros individuales
     */
    public static function delete($id) {
        global $wpdb;
        self::init();
        
        // Eliminar registros individuales
        $wpdb->delete(
            self::$rentals_table,
            array('monthly_rental_id' => $id),
            array('%d')
        );
        
        // Eliminar arriendo mensual
        $result = $wpdb->delete(
            self::$table_name,
            array('id' => $id),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Obtener nombre del día de la semana
     */
    public static function get_weekday_name($day_number) {
        $days = array(
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo'
        );
        return isset($days[$day_number]) ? $days[$day_number] : '';
    }
    
    /**
     * Obtener nombre del mes
     */
    public static function get_month_name($month_number) {
        $months = array(
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre'
        );
        return isset($months[$month_number]) ? $months[$month_number] : '';
    }
    
    /**
     * Formatear días de la semana para mostrar
     */
    public static function format_weekdays($weekdays_json) {
        $weekdays = is_array($weekdays_json) ? $weekdays_json : json_decode($weekdays_json, true);
        if (empty($weekdays)) return '';
        
        $names = array();
        foreach ($weekdays as $day) {
            $names[] = self::get_weekday_name($day);
        }
        return implode(', ', $names);
    }
    
    /**
     * Calcular precio mensual basado en precio por hora del box
     */
    public static function calculate_monthly_price($box_id, $month, $year, $weekdays, $start_time, $end_time) {
        global $wpdb;
        
        // Obtener precio por hora del box
        $box = $wpdb->get_row($wpdb->prepare(
            "SELECT price_per_hour FROM {$wpdb->prefix}mas_boxes WHERE id = %d",
            $box_id
        ));
        
        if (!$box || !$box->price_per_hour) {
            return 0;
        }
        
        $price_per_hour = floatval($box->price_per_hour);
        
        // Calcular horas por día
        $start = new DateTime($start_time);
        $end = new DateTime($end_time);
        $diff = $start->diff($end);
        $hours_per_day = $diff->h + ($diff->i / 60);
        
        // Contar días del mes que coinciden con los weekdays seleccionados
        $first_day = "{$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
        $last_day = date('Y-m-t', strtotime($first_day));
        
        $current = new DateTime($first_day);
        $end_date = new DateTime($last_day);
        $end_date->modify('+1 day');
        
        $days_count = 0;
        while ($current < $end_date) {
            $day_of_week = (int) $current->format('N'); // 1=Lunes, 7=Domingo
            if (in_array($day_of_week, $weekdays)) {
                $days_count++;
            }
            $current->modify('+1 day');
        }
        
        // Calcular precio total: precio_hora * horas_por_dia * cantidad_dias
        $total_price = $price_per_hour * $hours_per_day * $days_count;
        
        return round($total_price, 0); // Redondear a entero
    }
}
