# What is this?

This is a link checking robot, that crawls your moodle site following links
and reporting on links that are either broken or that link to very large
files.

# How does it work?

It is a local plugin with a moodle cron task, but it reaches into your moodle
via curl effectively from outside moodle, and scrapes each page, parses it and
follows links. By using this architecture it will only find broken links that
actually matter to students. Because it comes in from outside it needs to
authenticate and has a dependency on the [moodle-auth_basic](https://github.com/CatalystIT-AU/moodle-auth_basic"). It is
recommended that you setup a dedicated 'robot' user who has readonly access to
all the site pages you wish to crawl. You should give the robot similar read
 capabilities that real students will have but no write capabilities.

# Install

Install dependency plugin [moodle-auth_basic](https://github.com/CatalystIT-AU/moodle-auth_basic") as a git submodule:
```
git submodule add https://github.com/CatalystIT-AU/moodle-auth_basic.git auth/basic
```
Install plugin moodle-local_linkchecker_robot as a git submodule:
```
git submodule add https://github.com/central-queensland-uni/moodle-local_linkchecker_robot.git local/linkchecker_robot
```
Install it the same as any other moodle plugin.

https://docs.moodle.org/30/en/Installing_add-ons

https://moodle.org/plugins/auth_basic

https://github.com/CatalystIT-AU/moodle-auth_basic.git

# Configure

The default settings should be enough to get the robot itself to work but
ensure you change the robot users password. After configuring the robot
ensure that the robot user exists, there is a helper page here:

/local/linkchecker_robot/index.php

You can also test it from the CLI using curl, see this example:

https://github.com/CatalystIT-AU/moodle-auth_basic.git#curl-example

Once this works test running the robot task from the CLI:

php admin/tool/task/cli/schedule_task.php  --execute='\local_linkchecker_robot\task\crawl_task'

If this worked then it's a matter of sitting back and waiting for the
robot to do it's thing. It works incrementally spreading the load over many
cron cycles, you can watch it's progress in

/local/linkchecker_robot/report.php?report=queued

and

/local/linkchecker_robot/report.php?report=recent

# Reporting

4 new admin reports are available for showing the current crawl status, broken links
and slow links. They are available under:

Administration > Reports > Link checker

# Issues andFeedback

Please raise any issues in github:

https://github.com/central-queensland-uni/moodle-local_linkchecker_robot/issues

If you need anything urgently and would like to sponsor it's implemenation please
email me: Brendan Heywood brendan@catalyst-au.net


