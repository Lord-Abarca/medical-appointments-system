<?php
/**
 * Shortcode para mostrar profesionales activos
 */

if (!defined('ABSPATH')) {
    exit;
}

// Obtener profesionales activos
$mas_professionals = new MAS_Professionals();
$mas_services = new MAS_Services();
$professionals = $mas_professionals->get_professionals('active');

?>
<!DOCTYPE html>
<html>
<head>
    <style>
        .mas-professionals-grid {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .mas-professionals-title {
            text-align: center;
            font-size: 2.5rem;
            color: #1a2838;
            margin-bottom: 15px;
            font-weight: 700;
        }
        
        .mas-professionals-subtitle {
            text-align: center;
            font-size: 1.1rem;
            color: #6b7280;
            margin-bottom: 50px;
        }
        
        .mas-professionals-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }
        
        .mas-professional-card {
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
        }
        
        .mas-professional-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12);
        }
        
        .mas-professional-image-container {
            width: 100%;
            height: 320px;
            overflow: hidden;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
        }
        
        .mas-professional-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .mas-professional-card:hover .mas-professional-image {
            transform: scale(1.05);
        }
        
        .mas-professional-image-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 80px;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 300;
        }
        
        .mas-professional-content {
            padding: 24px;
        }
        
        .mas-professional-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1a2838;
            margin: 0 0 8px 0;
        }
        
        .mas-professional-specialty {
            font-size: 1rem;
            color: #10b981;
            font-weight: 600;
            margin: 0 0 16px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .mas-professional-bio {
            font-size: 0.95rem;
            color: #6b7280;
            line-height: 1.6;
            margin: 0 0 16px 0;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        /* Added styles for services section */
        .mas-professional-services {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
        }
        
        .mas-professional-services-title {
            font-size: 0.85rem;
            font-weight: 600;
            color: #1a2838;
            margin: 0 0 10px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .mas-professional-services-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 0;
            padding: 0;
            list-style: none;
        }
        
        .mas-professional-service-tag {
            display: inline-block;
            padding: 6px 12px;
            background: #f0fdf4;
            color: #166534;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            border: 1px solid #bbf7d0;
        }
        
        .mas-no-professionals {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
            font-size: 1.1rem;
        }
        
        @media (max-width: 768px) {
            .mas-professionals-container {
                grid-template-columns: 1fr;
            }
            
            .mas-professionals-title {
                font-size: 2rem;
            }
            
            .mas-professional-image-container {
                height: 280px;
            }
        }
    </style>
</head>
<body>
    <div class="mas-professionals-grid">
        <h2 class="mas-professionals-title">Nuestro Equipo de Profesionales</h2>
        <p class="mas-professionals-subtitle">Conoce a los especialistas que forman parte de NovaEspacio</p>
        
        <?php if (!empty($professionals)): ?>
            <div class="mas-professionals-container">
                <?php foreach ($professionals as $professional): ?>
                    <?php
                    $professional_services = $mas_services->get_professional_services($professional->id);
                    ?>
                    <div class="mas-professional-card">
                        <div class="mas-professional-image-container">
                            <?php if (!empty($professional->image_url)): ?>
                                <img 
                                    src="<?php echo esc_url($professional->image_url); ?>" 
                                    alt="<?php echo esc_attr($professional->display_name); ?>"
                                    class="mas-professional-image"
                                >
                            <?php else: ?>
                                <div class="mas-professional-image-placeholder">
                                    <?php echo esc_html(substr($professional->first_name, 0, 1) . substr($professional->last_name, 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mas-professional-content">
                            <h3 class="mas-professional-name">
                                <?php echo esc_html($professional->display_name); ?>
                            </h3>
                            
                            <?php if (!empty($professional->specialty)): ?>
                                <p class="mas-professional-specialty">
                                    <?php echo esc_html($professional->specialty); ?>
                                </p>
                            <?php endif; ?>
                            
                            <?php if (!empty($professional->bio)): ?>
                                <p class="mas-professional-bio">
                                    <?php echo esc_html($professional->bio); ?>
                                </p>
                            <?php endif; ?>
                            
                            <?php // Display services section ?>
                            <?php if (!empty($professional_services)): ?>
                                <div class="mas-professional-services">
                                    <h4 class="mas-professional-services-title">Servicios</h4>
                                    <ul class="mas-professional-services-list">
                                        <?php foreach ($professional_services as $service): ?>
                                            <li class="mas-professional-service-tag">
                                                <?php echo esc_html($service->service_name); ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="mas-no-professionals">
                <p>No hay profesionales disponibles en este momento.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
