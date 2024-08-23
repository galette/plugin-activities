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

namespace GaletteActivities\Repository;

use Analog\Analog;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Predicate;
use Laminas\Db\Sql\Predicate\PredicateSet;
use Galette\Core\Login;
use Galette\Core\Db;
use Galette\Entity\Adherent;
use Galette\Entity\Group;
use Galette\Repository\Groups;
use GaletteActivities\Entity\Activity;
use GaletteActivities\Entity\Subscription;
use GaletteActivities\Filters\SubscriptionsList;
use Laminas\Db\Sql\Select;

/**
 * Subscription
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Subscriptions
{
    private Db $zdb;
    private SubscriptionsList $filters;
    private int $count;
    private float $sum;

    public const ORDERBY_ACTIVITY = 0;
    public const ORDERBY_MEMBER = 1;
    public const ORDERBY_SUBSCRIPTIONDATE = 2;
    public const ORDERBY_ENDDATE = 3;
    public const ORDERBY_PAID = 3;

    public const FILTER_DC_PAID = 0;
    public const FILTER_PAID = 1;
    public const FILTER_NOT_PAID = 2;

    /**
     * Constructor
     *
     * @param Db                 $zdb     Database instance
     * @param ?SubscriptionsList $filters Filtering
     */
    public function __construct(Db $zdb, SubscriptionsList $filters = null)
    {
        $this->zdb = $zdb;

        if ($filters === null) {
            $this->filters = new SubscriptionsList();
        } else {
            $this->filters = $filters;
        }
    }

    /**
     * Get subscriptions list
     *
     * @param bool $full Export full list (no pagination), defaults to false
     *
     * @return array<Subscription>
     */
    public function getList(bool $full = false): array
    {
        try {
            $select = $this->buildSelect(null);
            $select->order($this->buildOrderClause());

            $this->proceedCount($select);

            if ($full !== true) {
                $this->filters->setLimits($select);
            }
            $results = $this->zdb->execute($select);
            $this->filters->query = $this->zdb->query_string;

            $subscriptions = [];
            foreach ($results as $row) {
                $subscription = new Subscription($this->zdb, $row);
                $subscriptions[] = $subscription;
            }

            return $subscriptions;
        } catch (\Exception $e) {
            Analog::log(
                'Cannot list subscription | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Builds the SELECT statement
     *
     * @param ?array<string> $fields fields list to retrieve
     * @param bool           $count  true if we want to count members
     *                               (not applicable from static calls), defaults to false
     *
     * @return Select SELECT statement
     */
    private function buildSelect(?array $fields, bool $count = false): Select
    {
        try {
            $fieldsList = ['*'];
            if (is_array($fields) && count($fields)) {
                $fieldsList = $fields;
            }

            $select = $this->zdb->select(ACTIVITIES_PREFIX . Subscription::TABLE, 's');
            $select->columns($fieldsList);

            $select->join(
                array('a' => PREFIX_DB . Adherent::TABLE),
                's.' . Adherent::PK . '= a.' . Adherent::PK
            );
            $select->join(
                array('ac' => PREFIX_DB . ACTIVITIES_PREFIX . Activity::TABLE),
                's.' . Activity::PK . '= ac.' . Activity::PK
            );

            $this->buildWhereClause($select);
            $select->order(self::buildOrderClause());

            $this->calculateSum($select);

            if ($count) {
                $this->proceedCount($select);
            }

            return $select;
        } catch (\Exception $e) {
            Analog::log(
                'Cannot build SELECT clause for subscriptions | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Calculate sum of all selected subscriptions
     *
     * @param Select $select Original select
     *
     * @return void
     */
    private function calculateSum(Select $select): void
    {
        try {
            $sumSelect = clone $select;
            $sumSelect->reset($sumSelect::COLUMNS);
            $joins = $sumSelect->joins;
            $sumSelect->reset($sumSelect::JOINS);
            foreach ($joins as $join) {
                $sumSelect->join(
                    $join['name'],
                    $join['on'],
                    [],
                    $join['type']
                );
                unset($join['columns']);
            }

            $sumSelect->reset($sumSelect::ORDER);
            $sumSelect->columns(
                array(
                    'sum' => new Expression('SUM(payment_amount)')
                )
            );

            $results = $this->zdb->execute($sumSelect);
            $result = $results->current();

            $this->sum = round((float)$result->sum, 2);
        } catch (\Exception $e) {
            Analog::log(
                'Cannot calculate subscriptions sum | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Builds where clause, for filtering on simple list mode
     *
     * @param Select $select Original select
     *
     * @return void
     */
    private function buildWhereClause(Select $select): void
    {
        try {
            switch ($this->filters->paid_filter) {
                case self::FILTER_PAID:
                    $select->where('is_paid = true');
                    break;
                case self::FILTER_NOT_PAID:
                    $select->where('is_paid = false');
                    break;
                case self::FILTER_DC_PAID:
                    //nothing to do here.
                    break;
            }

            if (
                $this->filters->activity_filter !== null
                && $this->filters->activity_filter != -1
            ) {
                $select->where(['s.' . Activity::PK => $this->filters->activity_filter]);
            }

            if (
                $this->filters->payment_type_filter !== null &&
                $this->filters->payment_type_filter != -1
            ) {
                $select->where->equalTo(
                    'payment_method',
                    $this->filters->payment_type_filter
                );
            }

            if ($this->filters->member_filter !== null) {
                $select->where->equalTo(
                    'a.' . Adherent::PK,
                    $this->filters->member_filter
                );
            }

            switch ($this->filters->date_field) {
                case SubscriptionsList::DATE_SUBSCRIPTION:
                    $field = 'subscription_date';
                    break;
                case SubscriptionsList::DATE_END:
                default:
                    $field = 'end_date';
                    break;
            }

            if ($this->filters->start_date_filter != null) {
                $d = new \DateTime($this->filters->rstart_date_filter);
                $select->where->greaterThanOrEqualTo(
                    $field,
                    $d->format('Y-m-d')
                );
            }

            if ($this->filters->end_date_filter != null) {
                $d = new \DateTime($this->filters->rend_date_filter);
                $select->where->lessThanOrEqualTo(
                    $field,
                    $d->format('Y-m-d')
                );
            }

            if (count($this->filters->selected)) {
                $select->where([Subscription::PK => $this->filters->selected]);
            }
        } catch (\Exception $e) {
            Analog::log(
                __METHOD__ . ' | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Is field allowed to order? it should be present in
     * provided fields list (those that are SELECT'ed).
     *
     * @param string         $field_name Field name to order by
     * @param ?array<string> $fields     SELECTE'ed fields
     *
     * @return boolean
     */
    private function canOrderBy(string $field_name, ?array $fields): bool
    {
        if (!is_array($fields)) {
            return true;
        } elseif (in_array($field_name, $fields)) {
            return true;
        } else {
            Analog::log(
                'Trying to order by ' . $field_name  . ' while it is not in ' .
                'selected fields.',
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Builds the order clause
     *
     * @param array<string> $fields Fields list to ensure ORDER clause
     *                              references selected fields. Optional.
     *
     * @return array<string> SQL ORDER clauses
     */
    private function buildOrderClause(array $fields = null): array
    {
        $order = array();

        switch ($this->filters->orderby) {
            case self::ORDERBY_ACTIVITY:
                if ($this->canOrderBy(Activity::PK, $fields)) {
                    $order[] = 'ac.name ' . $this->filters->getDirection();
                }
                break;
            case self::ORDERBY_MEMBER:
                if ($this->canOrderBy(Adherent::PK, $fields)) {
                    $order[] = 'a.nom_adh ' . $this->filters->getDirection() .
                                ', a.prenom_adh ' . $this->filters->getDirection();
                }
                break;
            case self::ORDERBY_SUBSCRIPTIONDATE:
                if ($this->canOrderBy('subscription_date', $fields)) {
                    $order[] = 'subscription_date ' . $this->filters->getDirection();
                }
                break;

            case self::ORDERBY_ENDDATE:
                if ($this->canOrderBy('end_date', $fields)) {
                    $order[] = 'end_date ' . $this->filters->getDirection();
                }
                break;
            case self::ORDERBY_PAID:
                if ($this->canOrderBy('id_paid', $fields)) {
                    $order[] = 'is_paid ' . $this->filters->getDirection();
                }
                break;
        }

        return $order;
    }

    /**
     * Count activities from the query
     *
     * @param Select $select Original select
     *
     * @return void
     */
    private function proceedCount(Select $select): void
    {
        try {
            $countSelect = clone $select;
            $countSelect->reset($countSelect::COLUMNS);
            $countSelect->reset($countSelect::ORDER);
            $countSelect->reset($countSelect::HAVING);
            $joins = $countSelect->joins;
            $countSelect->reset($countSelect::JOINS);
            foreach ($joins as $join) {
                $countSelect->join(
                    $join['name'],
                    $join['on'],
                    [],
                    $join['type']
                );
                unset($join['columns']);
            }

            $countSelect->columns(
                array(
                    'count' => new Expression('count(DISTINCT s.' . Subscription::PK . ')')
                )
            );

            $have = $select->having;
            if ($have->count() > 0) {
                foreach ($have->getPredicates() as $h) {
                    $countSelect->where($h);
                }
            }

            $results = $this->zdb->execute($countSelect);

            $this->count = (int)$results->current()->count;
            if (isset($this->filters) && $this->count > 0) {
                $this->filters->setCounter($this->count);
            }
        } catch (\Exception $e) {
            Analog::log(
                'Cannot count subscription | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Get count for current query
     *
     * @return int
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * Get sum
     *
     * @return double
     */
    public function getSum(): float
    {
        return $this->sum;
    }
}
