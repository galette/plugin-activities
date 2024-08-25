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
 * Subscription tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Subscription extends GaletteTestCase
{
    protected int $seed = 20240817102541;

    /**
     * Cleanup after each test method
     *
     * @return void
     */
    public function tearDown(): void
    {
        $delete = $this->zdb->delete(ACTIVITIES_PREFIX . \GaletteActivities\Entity\Subscription::TABLE);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(ACTIVITIES_PREFIX . \GaletteActivities\Entity\Activity::TABLE);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Group::GROUPSUSERS_TABLE);
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
        $subscription = new \GaletteActivities\Entity\Subscription($this->zdb);

        $this->assertNull($subscription->getId());
        $this->assertNull($subscription->getActivityId());
        $this->assertNull($subscription->getActivity());
        $this->assertNull($subscription->getMemberId());
        $this->assertNull($subscription->getMember());
        $this->assertFalse($subscription->isPaid());
        $this->assertNull($subscription->getAmount());
        $this->assertSame(6, $subscription->getPaymentMethod());
        $this->assertSame('Other', $subscription->getPaymentMethodName());
        $this->assertNotSame('', $subscription->getCreationDate());
        $this->assertSame('', $subscription->getSubscriptionDate());
        $this->assertSame('', $subscription->getEndDate());
        $this->assertSame('', $subscription->getComment());
        $this->assertSame('subscription-notpaid', $subscription->getRowClass());
    }

    /**
     * Test add and update
     *
     * @return void
     */
    public function testCrud(): void
    {
        $subscription = new \GaletteActivities\Entity\Subscription($this->zdb);
        $subscriptions = new \GaletteActivities\Repository\Activities($this->zdb, $this->login, $this->preferences);

        //ensure the table is empty
        $this->assertCount(0, $subscriptions->getList());

        //bootstrap data
        $activity = new \GaletteActivities\Entity\Activity($this->zdb);
        $data = [
            'name' => 'Activity for subscriptions',
            'comment' => 'Comment ' . $this->seed,
            'price' => 42.0,
        ];
        $this->assertTrue($activity->check($data));
        $this->assertTrue($activity->store());

        $group = new \Galette\Entity\Group();
        $group->setName('Subscribed group');
        $this->assertTrue($group->store());

        $gactivity = new \GaletteActivities\Entity\Activity($this->zdb);
        $data = [
            'name' => 'Activity with a group',
            'comment' => 'Comment for group/activity ' . $this->seed,
            'price' => 5.0,
            \Galette\Entity\Group::PK => $group->getId()
        ];
        $this->assertTrue($gactivity->check($data));
        $this->assertTrue($gactivity->store());

        $activity_id = $activity->getId();
        $gactivity_id = $gactivity->getId();
        $member_one = $this->getMemberOne();

        //Missing required data
        $data = [
        ];
        $this->assertFalse($subscription->check($data));
        $this->assertSame(
            [
                'Activity is mandatory',
                'Member is mandatory',
                'Subscription date is mandatory',
                'End date is mandatory'
            ],
            $subscription->getErrors()
        );

        $data = [
            'activity' => $activity_id,
        ];
        $this->assertFalse($subscription->check($data));
        $this->assertSame(
            [
                'Member is mandatory',
                'Subscription date is mandatory',
                'End date is mandatory'
            ],
            $subscription->getErrors()
        );

        $data = [
            'activity' => $activity_id,
            'member' => $member_one->id,
        ];
        $this->assertFalse($subscription->check($data));
        $this->assertSame(
            [
                'Subscription date is mandatory',
                'End date is mandatory'
            ],
            $subscription->getErrors()
        );

        $data = [
            'activity' => $activity_id,
            'member' => $member_one->id,
            'subscription_date' => 'notadate',
        ];
        $this->assertFalse($subscription->check($data));
        $this->assertSame(
            [
                '- Wrong date format (Y-m-d) for Subscription date!',
                'End date is mandatory'
            ],
            $subscription->getErrors()
        );

        $data = [
            'activity' => $activity_id,
            'member' => $member_one->id,
            'subscription_date' => '2024-08-17',
            'end_date' => '2025-08-17',
            'comment' => 'Comment ' . $this->seed,
        ];
        $this->assertTrue($subscription->check($data));
        $this->assertSame([], $subscription->getErrors());
        $this->assertTrue($subscription->store());
        $subscription_id = $subscription->getId();

        //member is not part of any group
        $this->assertCount(0,$member_one->getGroups());

        $this->assertFalse($subscription->isPaid());
        //by default, amount is set to activity price
        $this->assertSame(42.0, $subscription->getAmount());
        $this->assertSame('2024-08-17', $subscription->getSubscriptionDate());
        $this->assertSame('2025-08-17', $subscription->getEndDate());
        $this->i18n->changeLanguage('fr_FR');
        $this->assertSame('17/08/2024', $subscription->getSubscriptionDate());
        $this->assertSame('17/08/2025', $subscription->getEndDate());
        $this->i18n->changeLanguage('en_US');
        $this->assertSame('subscription-notpaid', $subscription->getRowClass());
        $this->assertSame($activity_id, $subscription->getActivityId());
        $this->assertSame($member_one->id, $subscription->getMemberId());
        $this->assertEquals($activity, $subscription->getActivity());
        $this->assertSame($member_one->id, $subscription->getMember()->id);

        //reload
        $subscription = new \GaletteActivities\Entity\Subscription($this->zdb, $subscription_id);
        $data += [
            'paid' => 1,
            'payment_amount' => 21.0,
            'payment_method' => 1,
        ];
        $this->assertTrue($subscription->check($data));
        $this->assertTrue($subscription->store());

        $this->assertTrue($subscription->isPaid());
        $this->assertSame(21.0, $subscription->getAmount());
        $this->assertSame('subscription-paid', $subscription->getRowClass());
        $this->assertSame(1, $subscription->getPaymentMethod());
        $this->assertSame('Cash', $subscription->getPaymentMethodName());

        //remove subscription
        $this->assertTrue($subscription->remove());
        $this->assertFalse($activity->load($subscription_id));

        //create a subscription with a group
        $subscription = new \GaletteActivities\Entity\Subscription($this->zdb);
        $data = [
            'activity' => $gactivity_id,
            'member' => $member_one->id,
            'subscription_date' => (new \DateTime())->format('Y-m-d'),
            'end_date' => (new \DateTime())->modify('+1 year')->format('Y-m-d'),
            'comment' => 'Comment ' . $this->seed,
        ];
        $this->assertTrue($subscription->check($data));
        $this->assertTrue($subscription->store());

        //member is part activity linked group
        $member_one->loadGroups();
        $groups = $member_one->getGroups();
        $this->assertCount(1, $groups);
    }

    /**
     * Test load error
     *
     * @return void
     */
    public function testLoadError(): void
    {
        $subscription = new \GaletteActivities\Entity\Subscription($this->zdb);
        $this->assertFalse($subscription->load(999));
    }
}
