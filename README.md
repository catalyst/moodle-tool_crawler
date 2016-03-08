# What is this?

This is a link checking robot, that crawls your moodle site following links
and reporting on links that are either broken or that link to very large
files.

# How does it work?

It is a local plugin with a moodle cron task, but it reaches into your moodle
via curl effectively from outside moodle, and scrapes each page, parses it and
follows links. By using this architecture it will only find broken links that
actually matter to students. Because it comes in from outside it needs to
authenticate and has a dependancy on the moodle-auth_basic plugin. It is
recommended that you setup a dedicated 'robot' user who has readonly access to
all the the site pages you wish to crawl. You should give the robot similar
capabilites that real students will have.

# Install

Easiest is to install using git as a submodule:

git submodule add git@github.com:central-queensland-uni/moodle-local_linkchecker_robot.git local/linkchecker_robot

Install it the same as any other moodle plugin, and remember to also install moodle
auth-basic plugin as well.

https://docs.moodle.org/30/en/Installing_add-ons

https://moodle.org/plugins/auth_basic

https://github.com/brendanheywood/moodle-auth_basic

# Reporting

4 new admin reports are available for showing the current crawl status, broken links
and slow links. They are available under:

Administration > Reports > Link checker

# Issues andFeedback

Please raise any issues in github:

https://github.com/central-queensland-uni/moodle-local_linkchecker_robot/issues

If you need anything urgently and would like to sponsor it's implemenation please
email me: Brendan Heywood brendan@catalyst-au.net


