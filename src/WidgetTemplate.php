<?php
/**
 * Skin to use for GPML widgets. Almost cut-n-paste from Brad Jorsch's
 * SkinApiTemplate class in core MW.
 *
 * Copyright (C) 2014  Brad Jorsch <bjorsch@wikimedia.org>
 * Copyright (C) 2018  J. David Gladstone Institutes
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Brad Jorsch <bjorsch@wikimedia.org>
 * @author Mark A. Hershberger <mah@nichework.com>
 */
namespace WikiPathways\GPML;

/**
 * BaseTemplate class for the 'widgetoutput' skin
 */
class WidgetTemplate extends SkinApiTemplate {

	public function execute() {
		$this->html( 'headelement' ) ?>

		<div class="mw-body" role="main">
			<div class="mw-body-content">
				<?php $this->html( 'bodytext' ) ?>
			</div>
		</div>

		<?php $this->printTrail() ?>
		</body></html>
<?php
	}
}
