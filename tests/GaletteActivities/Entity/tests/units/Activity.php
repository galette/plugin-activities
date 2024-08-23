<?php

/**
 * Copyright © 2003-2024 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace GaletteActivities\tests\units;

use Galette\GaletteTestCase;

/**
 * Activity tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Activity extends GaletteTestCase
{
    protected int $seed = 20240817102541;

    /**
     * Cleanup after each test method
     *
     * @return void
     */
    public function tearDown(): void
    {
        $delete = $this->zdb->delete(ACTIVITIES_PREFIX . \GaletteActivities\Entity\Activity::TABLE);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Group::TABLE);
        $this->zdb->execute($delete);

        parent::tearDown();
    }

    /**
     * Test empty
     *
     * @return void
     */
    public function testEmpty(): void
    {
        $activity = new \GaletteActivities\Entity\Activity($this->zdb);

        $this->assertNull($activity->getId());
        $this->assertSame('',$activity->getName());
        $this->assertSame('',$activity->getCreationDate());
        $this->assertNull($activity->getPrice());
        $this->assertSame('', $activity->getComment());
    }

    /**
     * Test add and update
     *
     * @return void
     */
    public function testCrud(): void
    {
        $activity = new \GaletteActivities\Entity\Activity($this->zdb);
        $activities = new \GaletteActivities\Repository\Activities($this->zdb, $this->login, $this->preferences);

        //ensure the table is empty
        $this->assertCount(0, $activities->getList());

        //required activity name
        $data = [
            'comment' => 'Test comment',
        ];
        $this->assertFalse($activity->check($data));
        $this->assertSame(['Name is mandatory'], $activity->getErrors());

        //required activity name
        $data = [
            'name' => 'Test activity',
            'comment' => 'Test comment',
            'type' => '1234',
        ];
        $this->assertFalse($activity->check($data));
        $this->assertSame(['Type is too long'], $activity->getErrors());

        //add new activity
        $data = [
            'name' => 'Test activity',
            'comment' => 'Test comment',
            'type' => 'one'
        ];
        $this->assertTrue($activity->check($data));
        $this->assertTrue($activity->store());
        $first_id = $activity->getId();
        $this->assertGreaterThan(0, $first_id);

        $this->assertTrue($activity->load($first_id));
        $this->assertSame('Test activity', $activity->getName());
        $this->assertSame('Test comment', $activity->getComment());
        $this->assertSame('one', $activity->getType());
        //FIXME: lang must be changed to have a different date format
        $this->assertNotSame('', $activity->getCreationDate());
        $this->assertNull($activity->getPrice());
        $this->assertNull($activity->getGroup());

        $activities_list = $activities->getList();
        $this->assertCount(1, $activities_list);
        $this->assertSame(1, $activities->getCount());
        $lactivity = $activities_list[0];
        $this->assertInstanceOf(\GaletteActivities\Entity\Activity::class, $lactivity);
        $this->assertEquals($activity, $lactivity);

        //edit activity
        $data['name'] = 'Test activity edited';
        $data['price'] = 10.5;
        $data['comment'] = '';
        $this->assertTrue($activity->check($data));
        $this->assertTrue($activity->store());
        $this->assertTrue($activity->load($first_id));

        $this->assertSame('Test activity edited', $activity->getName());
        $this->assertSame(10.5, $activity->getPrice());
        $this->assertSame('', $activity->getComment());

        $group = new \Galette\Entity\Group();
        $group->setName('Test group' . $this->seed);
        $this->assertTrue($group->store());
        $data['id_group'] = $group->getId();
        $this->assertTrue($activity->check($data));
        $this->assertTrue($activity->store());
        $activity = new \GaletteActivities\Entity\Activity($this->zdb, $first_id);

        $this->assertInstanceOf(\Galette\Entity\Group::class, $activity->getGroup());
        $this->assertSame($group->getId(), $activity->getGroup()->getId());

        //remove activity
        $this->assertTrue($activity->remove());
        $this->assertFalse($activity->load($first_id));
    }

    /**
     * Test load error
     *
     * @return void
     */
    public function testLoadError(): void
    {
        $activity = new \GaletteActivities\Entity\Activity($this->zdb);
        $this->assertFalse($activity->load(999));
    }
}
