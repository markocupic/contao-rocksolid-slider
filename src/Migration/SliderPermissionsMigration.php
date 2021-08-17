<?php
/*
 * Copyright MADE/YOUR/DAY OG <mail@madeyourday.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MadeYourDay\RockSolidSlider\Migration;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use MadeYourDay\RockSolidSlider\Slider;

/**
 * @internal
 */
class SliderPermissionsMigration extends AbstractMigration
{
	/**
	 * @var Connection
	 */
	private $connection;

	/**
	 * @var ContaoFramework
	 */
	private $framework;

	public function __construct(Connection $connection, ContaoFramework $framework)
	{
		$this->connection = $connection;
		$this->framework = $framework;
	}

	public function shouldRun(): bool
	{
		$schemaManager = $this->connection->getSchemaManager();

		if (
			!$schemaManager->tablesExist('tl_rocksolid_slider')
			|| !$schemaManager->tablesExist('tl_user')
			|| !$schemaManager->tablesExist('tl_user_group')
		) {
			return false;
		}

		$columnsUser = $schemaManager->listTableColumns('tl_user');
		$columnsGroup = $schemaManager->listTableColumns('tl_user_group');

		if (
			isset($columnsUser['rsts_sliders'])
			|| isset($columnsUser['rsts_permissions'])
			|| isset($columnsGroup['rsts_sliders'])
			|| isset($columnsGroup['rsts_permissions'])
		) {
			return false;
		}

		$this->framework->initialize();

		return Slider::checkLicense();
	}

	public function run(): MigrationResult
	{
		$defaultPermissions = serialize(['create', 'delete']);
		$defaultSliders = serialize(array_values(array_map(function ($row) {
			return $row['id'];
		}, $this->connection->fetchAllAssociative("SELECT id FROM tl_rocksolid_slider"))));

		foreach (['tl_user', 'tl_user_group'] as $table) {
			foreach ([
				"ALTER TABLE $table ADD rsts_permissions BLOB DEFAULT NULL",
				"ALTER TABLE $table ADD rsts_sliders BLOB DEFAULT NULL",
			] as $query) {
				$this->connection->executeStatement($query);
			}
			$this->connection->executeStatement(
				"UPDATE $table SET rsts_permissions = ?, rsts_sliders = ?",
				[$defaultPermissions, $defaultSliders],
				[Types::BLOB, Types::BLOB]
			);
		}

		return $this->createResult(true);
	}
}
