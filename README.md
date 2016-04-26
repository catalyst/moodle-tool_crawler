# What is this?

This is a link checking robot, that crawls your moodle site following links
and reporting on links that are either broken or that link to very large
files.

# How does it work?

It is a local plugin with a moodle cron task. It logs into your moodle
via curl effectively from outside moodle. The cronjob scrapes each page,
parses it and follows links. By using this architecture it will only find
broken links that actually matter to students.

Since the plugin cronjob comes in from outside it needs to authenticate in Moodle.

# Installing Requirements

The plugin has a dependency on the [moodle-auth_basic](https://moodle.org/plugins/auth_basic).
To install the dependency plugin as a git submodule:
```
git submodule add https://github.com/CatalystIT-AU/moodle-auth_basic.git auth/basic
```

# Installing plugins source code

Install plugin moodle-local_linkchecker_robot as a git submodule:
```
git submodule add https://github.com/central-queensland-uni/moodle-local_linkchecker_robot.git local/linkchecker_robot
```
# Configure

FYI: [installing Moodle plugins](https://docs.moodle.org/30/en/Installing_add-ons)

## Step 1

Login to Moodle. You will be forwarded to URL http://your_moodle_website.com/admin/index.php with Plugins check.
There you should see plugins "Basic authentication" and "Link checker robot".

Click button "Upgrade Moodle database now" which should initiate plugins installation.

Now you should see page "Upgrading to new version" with plugins installation statuses and button "Continue".

After clicking "Continue" you will get to the page "New settings - Link checker robot".
While you may leave other settings default, you might want to setup a custom bot username
and make sure to change bot password.

**It is recommended that bot user should be kept with readonly access to all the site pages you wish to crawl.
You can give the robot similar read capabilities that real students have.
Never give your bot user write capabilities.**

After verifying all settings click "Save changes".

Now navigate to URL http://your_moodle_website.com/local/linkchecker_robot/index.php".
It will show some stats about the Link checker Robot.

Click "autocreate" button against "Bot user". This actually creates the user which you have
configured previously on page "New settings - Link checker robot".

You can ensure that bot user was created from page:
Home / ► Site administration / ► Users / ► Accounts / ► Browse list of users
Click "Link checker Robot".

# Testing

You can test it from the CLI using curl, see this example:

https://github.com/CatalystIT-AU/moodle-auth_basic#curl-example

Once this works test running the robot task from the CLI:

php admin/tool/task/cli/schedule_task.php  --execute='\local_linkchecker_robot\task\crawl_task'

If this worked then it's a matter of sitting back and waiting for the
robot to do it's thing. It works incrementally spreading the load over many
cron cycles, you can watch it's progress in

/local/linkchecker_robot/report.php?report=queued

and

/local/linkchecker_robot/report.php?report=recent

# Travis CI integration

This plugin is configured to be tested in Travis CI.

More docs will follow.

# Reporting

4 new admin reports are available for showing the current crawl status, broken links
and slow links. They are available under:

Administration > Reports > Link checker

# Issues and Feedback

Please raise any issues in github:

https://github.com/central-queensland-uni/moodle-local_linkchecker_robot/issues

If you need anything urgently and would like to sponsor it's implemenation please
email me: Brendan Heywood brendan@catalyst-au.net


