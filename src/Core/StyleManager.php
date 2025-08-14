<?php

namespace AnsyblSite\Core;

use AnsyblSite\Exceptions\ConfigException;

class StyleManager 
{
    private ConfigManager $configManager;
    private ConcurrentFileManager $fileManager;
    private array $compiledCSS = [];
    
    public function __construct(ConfigManager $configManager, ConcurrentFileManager $fileManager)
    {
        $this->configManager = $configManager;
        $this->fileManager = $fileManager;
    }
    
    public function generateCSS(): string
    {
        $activeTheme = $this->getActiveTheme();
        $theme = $this->getTheme($activeTheme);
        
        return $this->compileTheme($theme);
    }
    
    public function compileTheme(array $theme): string
    {
        $css = ":root {\n";
        
        if (isset($theme['variables'])) {
            foreach ($theme['variables'] as $variable => $value) {
                $css .= "  {$variable}: {$value};\n";
            }
        }
        
        $css .= "}\n";
        
        return $css;
    }
    
    public function compileFullStylesheet(): string
    {
        $css = '';
        
        // Add theme variables
        $css .= $this->generateCSS();
        $css .= "\n";
        
        // Add responsive CSS
        $css .= $this->generateResponsiveCSS();
        $css .= "\n";
        
        // Add custom CSS if enabled
        if ($this->isCustomCSSEnabled()) {
            $customCSS = $this->getCustomCSS();
            if (!empty($customCSS)) {
                $css .= "/* Custom CSS */\n";
                $css .= $customCSS;
                $css .= "\n";
            }
        }
        
        return $css;
    }
    
    public function generateResponsiveCSS(): string
    {
        $responsive = $this->getResponsiveSettings();
        $css = '';
        
        // Mobile styles
        if (isset($responsive['mobile_breakpoint'])) {
            $css .= "@media (max-width: {$responsive['mobile_breakpoint']}) {\n";
            $css .= "  .container { padding: 10px; }\n";
            $css .= "  .menu { display: block; }\n";
            $css .= "}\n\n";
        }
        
        // Tablet styles
        if (isset($responsive['tablet_breakpoint'])) {
            $css .= "@media (min-width: {$responsive['mobile_breakpoint']}) and (max-width: {$responsive['tablet_breakpoint']}) {\n";
            $css .= "  .container { padding: 20px; }\n";
            $css .= "  .grid { grid-template-columns: repeat(2, 1fr); }\n";
            $css .= "}\n\n";
        }
        
        // Desktop styles
        $css .= "@media (min-width: {$responsive['tablet_breakpoint']}) {\n";
        $css .= "  .container { padding: 30px; }\n";
        $css .= "  .grid { grid-template-columns: repeat(3, 1fr); }\n";
        $css .= "}\n";
        
        return $css;
    }
    
    public function saveCompiledCSS(string $filename): bool
    {
        $css = $this->compileFullStylesheet();
        $content = "/* Generated CSS - Do not edit manually */\n";
        $content .= "/* Generated at: " . date('c') . " */\n\n";
        $content .= $css;
        
        $path = $this->fileManager->getFilePath($filename);
        return file_put_contents($path, $content) !== false;
    }
    
    public function getActiveTheme(): string
    {
        $styles = $this->configManager->get('styles');
        return $styles['active_theme'] ?? 'default';
    }
    
    public function setActiveTheme(string $themeName): bool
    {
        if (!$this->hasTheme($themeName)) {
            throw new ConfigException("Theme not found: {$themeName}");
        }
        
        return $this->configManager->setValue('styles', 'active_theme', $themeName);
    }
    
    public function getThemes(): array
    {
        $styles = $this->configManager->get('styles');
        return $styles['themes'] ?? [];
    }
    
    public function getTheme(string $themeName): array
    {
        $themes = $this->getThemes();
        
        if (!isset($themes[$themeName])) {
            throw new ConfigException("Theme not found: {$themeName}");
        }
        
        return $themes[$themeName];
    }
    
    public function hasTheme(string $themeName): bool
    {
        $themes = $this->getThemes();
        return isset($themes[$themeName]);
    }
    
    public function addTheme(string $themeName, array $theme): bool
    {
        return $this->configManager->setValue('styles', "themes.{$themeName}", $theme);
    }
    
    public function updateThemeVariable(string $themeName, string $variable, string $value): bool
    {
        if (!$this->hasTheme($themeName)) {
            throw new ConfigException("Theme not found: {$themeName}");
        }
        
        return $this->configManager->setValue('styles', "themes.{$themeName}.variables.{$variable}", $value);
    }
    
    public function deleteTheme(string $themeName): bool
    {
        if ($themeName === $this->getActiveTheme()) {
            throw new \Exception("Cannot delete active theme: {$themeName}");
        }
        
        $styles = $this->configManager->get('styles');
        unset($styles['themes'][$themeName]);
        
        return $this->configManager->set('styles', $styles);
    }
    
    public function getCustomCSS(): string
    {
        try {
            return $this->configManager->getValue('styles', 'custom_css.css');
        } catch (ConfigException $e) {
            return '';
        }
    }
    
    public function setCustomCSS(string $css): bool
    {
        // Validate CSS syntax
        if (!$this->validateCSS($css)) {
            throw new \Exception("Invalid CSS syntax");
        }
        
        $result = $this->configManager->setValue('styles', 'custom_css.css', $css);
        
        if ($result) {
            $this->configManager->setValue('styles', 'custom_css.enabled', true);
        }
        
        return $result;
    }
    
    public function isCustomCSSEnabled(): bool
    {
        try {
            return $this->configManager->getValue('styles', 'custom_css.enabled');
        } catch (ConfigException $e) {
            return false;
        }
    }
    
    public function enableCustomCSS(bool $enabled = true): bool
    {
        return $this->configManager->setValue('styles', 'custom_css.enabled', $enabled);
    }
    
    public function getResponsiveSettings(): array
    {
        try {
            return $this->configManager->getValue('styles', 'responsive');
        } catch (ConfigException $e) {
            return [
                'mobile_breakpoint' => '768px',
                'tablet_breakpoint' => '1024px'
            ];
        }
    }
    
    public function updateResponsiveSettings(array $settings): bool
    {
        return $this->configManager->merge('styles', ['responsive' => $settings]);
    }
    
    public function validateCSS(string $css): bool
    {
        // Basic CSS validation - check for balanced braces
        $openBraces = substr_count($css, '{');
        $closeBraces = substr_count($css, '}');
        
        if ($openBraces !== $closeBraces) {
            return false;
        }
        
        // Check for basic syntax patterns
        $lines = explode("\n", $css);
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines and comments
            if (empty($line) || str_starts_with($line, '/*') || str_starts_with($line, '//')) {
                continue;
            }
            
            // Skip CSS rules and media queries
            if (str_contains($line, '{') || str_contains($line, '}') || 
                str_starts_with($line, '@') || str_starts_with($line, '.') || 
                str_starts_with($line, '#')) {
                continue;
            }
            
            // Check property declarations
            if (str_contains($line, ':') && !str_ends_with(rtrim($line), ';')) {
                // Property declaration should end with semicolon
                return false;
            }
        }
        
        return true;
    }
    
    public function minifyCSS(string $css): string
    {
        // Remove comments
        $css = preg_replace('/\/\*.*?\*\//s', '', $css);
        
        // Remove unnecessary whitespace
        $css = preg_replace('/\s+/', ' ', $css);
        
        // Remove spaces around special characters
        $css = preg_replace('/\s*([{}:;,>+~])\s*/', '$1', $css);
        
        // Remove trailing semicolon before closing brace
        $css = str_replace(';}', '}', $css);
        
        return trim($css);
    }
    
    public function getStats(): array
    {
        $themes = $this->getThemes();
        $customCSS = $this->getCustomCSS();
        $compiledCSS = $this->compileFullStylesheet();
        
        return [
            'total_themes' => count($themes),
            'active_theme' => $this->getActiveTheme(),
            'custom_css_enabled' => $this->isCustomCSSEnabled(),
            'custom_css_length' => strlen($customCSS),
            'compiled_css_size' => strlen($compiledCSS),
            'responsive_breakpoints' => count($this->getResponsiveSettings())
        ];
    }
    
    public function exportTheme(string $themeName): array
    {
        $theme = $this->getTheme($themeName);
        
        return [
            'name' => $theme['name'],
            'variables' => $theme['variables'] ?? [],
            'exported_at' => date('c'),
            'version' => '1.0'
        ];
    }
    
    public function importTheme(string $themeName, array $themeData): bool
    {
        // Validate theme structure
        if (!isset($themeData['name']) || !isset($themeData['variables'])) {
            throw new \Exception("Invalid theme data structure");
        }
        
        $theme = [
            'name' => $themeData['name'],
            'variables' => $themeData['variables'],
            'imported_at' => date('c')
        ];
        
        return $this->addTheme($themeName, $theme);
    }
}