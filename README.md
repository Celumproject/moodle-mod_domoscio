# Plugin Domoscio for Moodle

This plugin integrate the Domoscio algorithm to consolidate knowledge into Moodle.

[Documentation can be found here](https://s3-eu-west-1.amazonaws.com/domoscio-backedn/user_doc.PDF)

The solution comes with the Domoscio plugin contained into "domoscio" folder to be placed into the "mod" folder, and a Domoscio Reminder, useful for students, to be placed into "blocks" folder

[Get the Domoscio Reminder block](https://github.com/Celumproject/moodle-block_domoscioreminder)

This branch contains the plugin for Moodle 2.9

CHANGELOG :
- 2016062400:
  - Domoscio API access automatically during installation process = Better user experience
  - This give a free access to Domoscio service, unlimited in time, up to 30 students
  - Minor fixes for reported issues

- 2016040800:
  - Improved Test session navigation
  - Plugin own its dedicated question bank so users can create questions from scratch and assign them to the Plugin
  - Improve overall stability

- 2015112600 :
  - Plugin now in Spanish !
  - Alerts if API settings not completed properly
  - Minor improvements to course module info display
  - Various bugfixes

- As Moodle 2.9 already include jQuery library, this plugin version is not embedding jQuery anymore.

#LICENCE

Copyright (C) 2015 Domoscio SA. This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version. This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details. You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
