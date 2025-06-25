 **⚠️ Warning: Experimental Project**  
> Polydock Engine is currently in active development and has not yet reached a stable production release. This project should be considered experimental.
>
> If you are interested in using Polydock Engine in a production setting, please contact Bryan Gruneberg (bryan@workshoporange.co) from [Workshop Orange](https://www.workshoporange.co), one of the sponsoring organizations.

# Creating amazee.io Drupal Trials for Polydock

This guide explains how to create Drupal-based trial applications that can be deployed and managed by Polydock on amazee.io. 

## Notes
- This is a work in progress. Please help us improve it by opening issues and pull requests.
- If you have any questions, feedback, or feature requests, please reach out to us on Slack or email hello@workshoporange.co.

## Overview

Trial applications are separate repositories that contain:
1. The Drupal application code
2. Lagoon configuration
3. Special scripts for Polydock integration
4. Pre-configured content and settings

## Repository Structure

### Required Scripts
Your repository needs these key scripts in the `.lagoon/scripts/` directory:

1. **polydock_post_deploy.sh**
   - Runs after Polydock deploys a trial instance
   - Downloads and restores the app-data-image
   - Configures instance-specific settings
   - Sets up credentials and configuration

2. **polydock_claim.sh**
   - Runs when a user claims a trial
   - Generates one-time login URL
   - Returns URL to Polydock
   - Can perform additional setup

3. **create_polydock_app_image.sh**
   - Creates the app-data-image snapshot
   - Captures database and files
   - Used to create the base trial state

### Example Repository Structure
```
your-drupal-trial/
├── .lagoon/
│   └── scripts/
│       ├── polydock_post_deploy.sh
│       ├── polydock_claim.sh
│       ├── create_polydock_app_image.sh
│       └── trial_install_configure.sh
├── web/
└── composer.json
└── ... (other drupal stuff)
```

## Step-by-Step Setup

### 1. Create Repository
1. Create a new Git repository for your trial experience
2. Add required Lagoon configuration (see example repositories below as starting points)
3. Copy and adapt example scripts from existing repositories
4. (optional) Configure your Drupal through composer for modules you need 
5. (optional) You can do local dev and the sync that to the lagoon project

### 2. Deploy Base Instance
1. Create Lagoon project
2. Configure required variables
   ```bash
   # Example for AI-based trials
   lagoon add variable -p [PROJECT] -S global -N AI_LLM_API_URL -V [VALUE]
   lagoon add variable -p [PROJECT] -S global -N AI_LLM_API_TOKEN -V [VALUE]
   # ... additional variables as needed
   ```
3. Deploy initial instance

### 3. Configure Base State
1. Install Drupal (EG: ssh to the project, run drush si )
2. Configure settings (through the ui, or drush, or whatever float your boat)
3. Add content (EG: create content types, and then create content nodes of those types)
4. Set up required features (EG: enable modules, configure features, etc)
5. Test functionality (EG: make sure you can access the site, and that the site is working as expected)

### 4. Once the site is ready to be the base of other instances, create an app-data-image.tgz
1. SSH into Lagoon instance:
   ```bash
   lagoon ssh -p [PROJECT] -e main
   ```
2. Run create script:
   ```bash
   .lagoon/scripts/create_polydock_app_image.sh
   ```
3. Transfer to storage:
   ```bash
   lagoon ssh -p ai-trial-storage -e main -s nginx
   cd storage/[TRIAL-TYPE]
   wget https://[PROJECT-URL]/sites/default/files/polydock/app-data-image.tgz
   ```

### 5. Polydock Integration
Work with Polydock administrators to:
1. Create store app configuration
2. Set up trial parameters1
3. Configure email notifications
4. Test deployment process

## Script Requirements

### polydock_post_deploy.sh
```bash
#!/bin/bash
# Must:
# 1. Download app-data-image
# 2. Restore database
# 3. Configure instance settings
# 4. Return 0 on success
```

### polydock_claim.sh
```bash
#!/bin/bash
# Must:
# 1. Generate login URL
# 2. Print URL to stdout
# 3. Return 0 on success
```

## Example Trials
Reference implementations:
- [CK Editor AI Trial](https://github.com/amazeeio-demos/polydock-ai-trial-drupal-cms-ck-editor)
- [Content Categorization Trial](https://github.com/amazeeio-demos/polydock-ai-trial-drupal-cms-caegorize-page)
- [AI Search Trial](https://github.com/amazeeio-demos/polydock-ai-trial-drupal-cms-search)

## Best Practices

1. **Fast Deployment**
   - Avoid post-install or pre-install hooks in .lagoon.yml
   - Use app-data-image for db and files
   - Minimize runtime configuration as much as possible. Every second you wait to run your own code, the more time the user has to cancel the trial or browse away.

2. **Script Reliability**
   - Handle errors gracefully
   - Provide clear logging
   - Return appropriate exit codes

3. **Security**
   - Don't commit sensitive data!! Seriously, don't do it. Use Lagoon variables for everything that is different per instance.
   - Follow Drupal security best practices

4. **Testing**
   - Test scripts locally when possible
   - Verify image creation process
   - Validate claim flow

## Support
For assistance setting up new trials, contact:
- Workshop Orange for Polydock integration
- amazee.io for Lagoon support
