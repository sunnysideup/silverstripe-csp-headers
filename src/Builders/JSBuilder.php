<?php


namespace Firesphere\CSPHeaders\Builders;

use Firesphere\CSPHeaders\View\CSPBackend;
use GuzzleHttp\Exception\GuzzleException;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ValidationException;
use SilverStripe\View\HTML;

class JSBuilder
{
    /**
     * @var CSPBackend
     */
    protected $owner;
    /**
     * @var SRIBuilder
     */
    protected $sriBuilder;

    public function __construct($backend)
    {
        $this->owner = $backend;
        $this->sriBuilder = Injector::inst()->get(SRIBuilder::class);
    }

    /**
     * @param $attributes
     * @param $file
     * @param $jsRequirements
     * @param $path
     * @return string
     * @throws GuzzleException
     * @throws ValidationException
     */
    public function buildJSTag($attributes, $file, $jsRequirements, $path): string
    {
        // Build html attributes
        $htmlAttributes = array_merge([
            'type' => $attributes['type'] ?? 'application/javascript',
            'src'  => $path,
        ], $attributes);

        // Build SRI if it's enabled
        if (CSPBackend::isJsSRI()) {
            $htmlAttributes = $this->sriBuilder->buildSRI($file, $htmlAttributes);
        }
        // Use nonces for inlines if requested
        if (CSPBackend::isUsesNonce()) {
            $htmlAttributes['nonce'] = base64_encode(Controller::curr()->getNonce());
        }

        $jsRequirements .= HTML::createTag('script', $htmlAttributes);
        $jsRequirements .= "\n";

        // Add all inline JavaScript *after* including external files they might rely on
        foreach ($this->owner->getCustomScripts() as $script) {
            $options = ['type' => 'application/javascript'];
            if (CSPBackend::isUsesNonce()) {
                $options['nonce'] = Controller::curr()->getNonce();
            }
            $jsRequirements .= HTML::createTag(
                'script',
                $options,
                "//<![CDATA[\n{$script}\n//]]>"
            );
            $jsRequirements .= "\n";
        }

        return $jsRequirements;
    }
}
