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

namespace GaletteActivities\Filters;

use Analog\Analog;
use Galette\Core\Pagination;
use Galette\Helpers\DatesHelper;
use GaletteActivities\Repository\Subscriptions;

/**
 * Subscription lists filters and paginator
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property ?string $start_date_filter
 * @property ?string $end_date_filter
 * @property ?string $rstart_date_filter
 * @property ?string $rend_date_filter
 * @property ?int $activity_filter
 * @property ?int $member_filter
 * @property int $paid_filter
 * @property int $payment_type_filter
 * @property int $date_field
 */
class SubscriptionsList extends Pagination
{
    use DatesHelper;

    public const DATE_END = 0;
    public const DATE_SUBSCRIPTION = 1;
    //filters
    private string|int|null $activity_filter;
    private string|int|null $member_filter;

    private int|string $paid_filter;
    private int $payment_type_filter;
    private ?int $date_field = null;
    private ?string $start_date_filter;
    private ?string $end_date_filter;

    /** @var array<int> */
    private array $selected;
    private string $query;

    /** @var array<string> */
    protected array $list_fields = array(
        'activity_filter',
        'member_filter',
        'paid_filter',
        'payment_type_filter',
        'date_field',
        'start_date_filter',
        'end_date_filter',
        'selected'
    );

    /** @var array<string>  */
    protected array $virtuals_list_fields = array(
        'rstart_date_filter',
        'rend_date_filter'
    );

    /**
     * Default constructor
     */
    public function __construct()
    {
        $this->reinit();
    }

    /**
     * Returns the field we want to default set order to
     *
     * @return int|string field name
     */
    protected function getDefaultOrder(): int|string
    {
        return Subscriptions::ORDERBY_ENDDATE;
    }

    /**
     * Return the default direction for ordering
     *
     * @return string ASC or DESC
     */
    protected function getDefaultDirection(): string
    {
        return self::ORDER_DESC;
    }

    /**
     * Reinit default parameters
     *
     * @return void
     */
    public function reinit(): void
    {
        parent::reinit();
        $this->activity_filter = null;
        $this->member_filter = null;
        $this->paid_filter = Subscriptions::FILTER_DC_PAID;
        $this->payment_type_filter = -1;
        $this->selected = [];
        $this->date_field = self::DATE_END;
        $this->start_date_filter = null;
        $this->end_date_filter = null;
    }

    /**
     * Global getter method
     *
     * @param string $name name of the property we want to retrieve
     *
     * @return mixed the called property
     */
    public function __get(string $name): mixed
    {
        if (in_array($name, $this->pagination_fields)) {
            return parent::__get($name);
        } else {
            if (in_array($name, $this->list_fields) || in_array($name, $this->virtuals_list_fields)) {
                switch ($name) {
                    case 'start_date_filter':
                    case 'end_date_filter':
                        return $this->getDate($name);
                    case 'rstart_date_filter':
                    case 'rend_date_filter':
                        //same as above, but raw format
                        $rname = substr($name, 1);
                        return $this->getDate($rname, true, false);
                    default:
                        return $this->$name;
                }

            }
        }

        throw new \RuntimeException(
            sprintf(
                'Unable to get property "%s::%s"!',
                __CLASS__,
                $name
            )
        );
    }

    /**
     * Global setter method
     *
     * @param string $name  name of the property we want to assign a value to
     * @param mixed  $value a relevant value for the property
     *
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        if (in_array($name, $this->pagination_fields)) {
            parent::__set($name, $value);
        } else {
            Analog::log(
                '[SubscriptionsList] Setting property `' . $name . '`',
                Analog::DEBUG
            );

            switch ($name) {
                case 'start_date_filter':
                case 'end_date_filter':
                    $this->setFilterDate($name, $value, $name === 'start_date_filter');
                    break;
                case 'selected':
                    if (is_array($value)) {
                        $this->$name = $value;
                    } elseif ($value !== null) {
                        Analog::log(
                            '[SubscriptionsList] Value for property `' . $name .
                            '` should be an array (' . gettype($value) . ' given)',
                            Analog::WARNING
                        );
                    }
                    break;
                case 'payment_type_filter':
                case 'activity_filter':
                case 'member_filter':
                case 'date_field':
                    $this->$name = (int)$value;
                    break;
                default:
                    $this->$name = $value;
                    break;
            }
        }
    }
}
