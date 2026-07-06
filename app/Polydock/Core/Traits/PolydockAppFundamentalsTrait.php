<?php

namespace App\Polydock\Core\Traits;

use App\Polydock\Core\PolydockAppInterface;

trait PolydockAppFundamentalsTrait
{
    /**
     * Get the name of the app
     *
     * @return string The app name
     */
    public function getAppName(): string
    {
        return $this->appName;
    }

    /**
     * Set the name of the app
     *
     * @param  string  $appName  The name to set
     * @return PolydockAppInterface Returns the instance for method chaining
     */
    public function setAppName(string $appName): PolydockAppInterface
    {
        $this->appName = $appName;

        return $this;
    }

    /**
     * Get the description of the app
     *
     * @return string The app description
     */
    public function getAppDescription(): string
    {
        return $this->appDescription;
    }

    /**
     * Set the description of the app
     *
     * @param  string  $appDescription  The description to set
     * @return PolydockAppInterface Returns the instance for method chaining
     */
    public function setAppDescription(string $appDescription): PolydockAppInterface
    {
        $this->appDescription = $appDescription;

        return $this;
    }

    /**
     * Get the author of the app
     *
     * @return string The app author
     */
    public function getAppAuthor(): string
    {
        return $this->appAuthor;
    }

    /**
     * Set the author of the app
     *
     * @param  string  $appAuthor  The author to set
     * @return PolydockAppInterface Returns the instance for method chaining
     */
    public function setAppAuthor(string $appAuthor): PolydockAppInterface
    {
        $this->appAuthor = $appAuthor;

        return $this;
    }

    /**
     * Get the website URL of the app
     *
     * @return string The app website URL
     */
    public function getAppWebsite(): string
    {
        return $this->appWebsite;
    }

    /**
     * Set the website URL of the app
     *
     * @param  string  $appWebsite  The website URL to set
     * @return PolydockAppInterface Returns the instance for method chaining
     */
    public function setAppWebsite(string $appWebsite): PolydockAppInterface
    {
        $this->appWebsite = $appWebsite;

        return $this;
    }

    /**
     * Get the support email address for the app
     *
     * @return string The app support email
     */
    public function getAppSupportEmail(): string
    {
        return $this->appSupportEmail;
    }

    /**
     * Set the support email address for the app
     *
     * @param  string  $appSupportEmail  The support email to set
     * @return PolydockAppInterface Returns the instance for method chaining
     */
    public function setAppSupportEmail(string $appSupportEmail): PolydockAppInterface
    {
        $this->appSupportEmail = $appSupportEmail;

        return $this;
    }
}
