<?php

namespace Symfony\Cmf\Bundle\SeoBundle\Model;

use PHPCR\Util\UUIDHelper;
use Sonata\SeoBundle\Seo\SeoPage;
use Symfony\Cmf\Bundle\RoutingBundle\Doctrine\Phpcr\RedirectRoute;
use Symfony\Cmf\Bundle\RoutingBundle\Doctrine\Phpcr\Route;
use Symfony\Cmf\Bundle\SeoBundle\Exceptions\SeoAwareException;
use Symfony\Cmf\Bundle\SeoBundle\Exceptions\SeoExtractorStrategyException;
use Symfony\Cmf\Bundle\SeoBundle\Extractor\SeoExtractorStrategyInterface;
use Symfony\Cmf\Bundle\SeoBundle\Extractor\SeoOriginalRouteExtractorStrategy;
use Symfony\Cmf\Component\Routing\RouteReferrersInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * This presentation model prepares the data for the SeoPage service of the
 * SonataSeoBundle, which is able to provide the values to its Twig helpers.
 *
 * Preparing means combining the title value of the SeoMetadata and the default
 * value defined in the cmf_seo.title.default parameter. Both strings are
 * concatenated by an separator depending on the strategy set in the config.
 *
 * The content config under cmf_seo.content gives a strategy how to handle duplicate
 * content. If it is set to canonical a canonical link is created by an Twig helper
 * (url must be set to the SeoPage), otherwise the url is set to the redirect property
 * which triggers an redirect.
 *
 * @author Maximilian Berghoff <Maximilian.Berghoff@gmx.de>
 */
class SeoPresentation extends AbstractSeoPresentation
{
    /**
     * @var SeoPage
     */
    protected $sonataPage;

    /**
     * @var SeoMetadataInterface
     */
    protected $seoMetadata;

    /**
     * The constructor will set the injected SeoPage - the service of
     * sonata which is responsible for storing the seo data.
     *
     * @param SeoPage $sonataPage
     * @param array   $strategies
     */
    public function __construct(SeoPage $sonataPage, array $strategies)
    {
        $this->sonataPage = $sonataPage;
        $this->strategies = $strategies;
    }

    /**
     * This method is used to get the SeoMetadata from current content document.
     *
     * @throws \Symfony\Cmf\Bundle\SeoBundle\Exceptions\SeoExtractorStrategyException
     * @return SeoMetadata
     */
    protected function getSeoMetadata()
    {
        $seoMetadata = new SeoMetadata();

        //copy the documents metadata to the current one as default
        foreach (array('Title', 'MetaDescription', 'MetaKeywords', 'OriginalUrl') as $propertyName) {
            $seoMetadata->{'set'.$propertyName}($this->contentDocument->getSeoMetadata()->{'get'.$propertyName}());
        }

        foreach ($this->strategies as $strategy) {
            if (!$strategy instanceof SeoExtractorStrategyInterface) {
                print($strategy);
                throw new SeoExtractorStrategyException('Wrong Strategy given.');
            }

            if ($strategy->supports($this->contentDocument)) {
                $strategy->updateMetadata($this->contentDocument, $seoMetadata);
            }
        }

        return $seoMetadata;
    }

    /**
     *  this method will combine all settings directly in the sonata_seo configuration with
     *  the given values of the current content
     */
    public function setMetaDataValues()
    {
        //get the current seo metadata out of the document
        $this->seoMetadata = $this->getSeoMetadata();

        //based on the title strategy, the helper method will set the complete title
        if (null !== $this->seoMetadata->getTitle()) {
            $title = $this->createTitle();

            //set the title to SeoPage and  a meta field
            $this->sonataPage->setTitle($title);
            $this->sonataPage->addMeta('names', 'title', $title);
        }

        if (null !== $this->seoMetadata->getMetaDescription()) {
            $this->sonataPage->addMeta(
                'names',
                'description',
                $this->createDescription()
            );
        }

        if (null !== $this->seoMetadata->getMetaKeywords()) {
            $this->sonataPage->addMeta(
                'names',
                'keywords',
                $this->createKeywords()
            );
        }

        //if the strategy for duplicate content is canonical, the service will trigger an canonical link
        switch ($this->contentParameters['strategy']) {
            case 'canonical':
                $this->sonataPage->setLinkCanonical($this->seoMetadata->getOriginalUrl());
                break;
            case 'redirect':
                $this->setRedirect(
                    $this->createRedirectUrl($this->seoMetadata->getOriginalUrl())
                );
                break;
        }
    }

    /**
     * Based on the title strategy this method will create the title from the given
     * configs in the seo configuration part.
     *
     * @return string
     */
    private function createTitle()
    {
        $defaultTitle = $this->doMultilangDecision($this->titleParameters['default']);
        $separator = $this->titleParameters['separator'];
        $contentTitle = $this->seoMetadata->getTitle();

        if ('' == $defaultTitle) {
            return $contentTitle;
        }

        switch ($this->titleParameters['strategy']) {
            case 'prepend':
                return $contentTitle.$separator.$defaultTitle;
            case 'append':
                return $defaultTitle .$separator. $contentTitle;
            case 'replace':
                return $contentTitle;
            default:
                return $defaultTitle;
        }
    }

    /**
     * Depending on the current locale and the setting for the default title this
     * method will return the default title as a string.
     *
     * @param  array|string                                               $defaultTitle
     * @throws \Symfony\Cmf\Bundle\SeoBundle\Exceptions\SeoAwareException
     * @return array|string
     */
    private function doMultilangDecision($defaultTitle)
    {
        if (is_string($defaultTitle)) {
            return $defaultTitle;
        }

        // try the current location of the document, seoMetadata should have the same
        $currentLocale = $this->getModelLocale();
        if (is_array($defaultTitle) && isset($defaultTitle[$currentLocale])) {
            return $defaultTitle[$currentLocale];
        }

        //try the applications default locale
        $defaultLocale = $this->getApplicationDefaultLocale();
        if (is_array($defaultTitle) && isset($defaultTitle[$defaultLocale])) {
            return $defaultTitle[$defaultLocale];
        }

        throw new SeoAwareException(
            sprintf(
                'No default value of title found for current document locale %s and applications default %s',
                $currentLocale,
                $defaultLocale
            )
        );
    }

    /**
     * As you can set your default description in the sonata_seo settings and
     * can add some more from your contend, this method will combine both.
     *
     * @return string
     */
    private function createDescription()
    {
        $sonataDescription = isset($this->sonataPage->getMetas()['names']['description'][0])
                                ? $this->sonataPage->getMetas()['names']['description'][0]
                                : '';

        return ('' !== $sonataDescription ? $sonataDescription.'. ' : '').$this->seoMetadata->getMetaDescription();
    }

    /**
     * Same as for the previous method. You can set the keywords in your sonata seo
     * setting, but each SeoAwareContent is able to set its own, this method will combine
     * both.
     *
     * @return string
     */
    private function createKeywords()
    {
        $sonataKeywords = isset($this->sonataPage->getMetas()['names']['keywords'][0])
                                ? $this->sonataPage->getMetas()['names']['keywords'][0]
                                : '';

        return ('' !== $sonataKeywords ? $sonataKeywords.', ' : '').$this->seoMetadata->getMetaKeywords();
    }

    /**
     * As there are several ways to set the original route for a content,
     * there are several solutions needed to create a path for the redirect route
     * out of it.
     */
    private function createRedirectUrl($value)
    {
        $routeStrategy = new SeoOriginalRouteExtractorStrategy();
        if (!UUIDHelper::isUUID($value) &&
            !$routeStrategy->supports($this->contentDocument)
        ) {
            //The value is just a plain url
            return new RedirectResponse($value);
        }

        if (UUIDHelper::isUUID($value)) {
            //the value is the uuid of a route document, one of the documents routes was selected
            $routeDocument = $this->getDocumentManager()->find(null, $value);
        }

        if ($routeStrategy->supports($this->contentDocument)) {
            //than the value is a route document
            $routeDocument = $value;
        }

        //@todo add a method do extract the url out of the route document
        return new RedirectResponse('some_url_of_route');

    }
}
