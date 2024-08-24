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

namespace GaletteActivities\Entity;

use ArrayObject;
use DateTime;
use Galette\Core\Db;
use Galette\Core\Login;
use Galette\Entity\Adherent;
use Galette\Entity\PaymentType;
use Analog\Analog;
use Galette\Helpers\EntityHelper;

/**
 * Subscription entity
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Subscription
{
    use EntityHelper;

    public const TABLE = 'subscriptions';
    public const PK = 'id_subscription';

    private Db $zdb;
    /** @var array<string> */
    private array $errors;

    private int $id;
    private int $id_activity;
    private ?Activity $activity = null;
    private int $id_member;
    private ?Adherent $member = null;
    private bool $paid = false;
    private ?float $payment_amount = null;
    private int $payment_method = PaymentType::OTHER;
    private ?string $creation_date = null;
    private ?string $subscription_date = null;
    private ?string $end_date = null;
    private string $comment = '';

    /**
     * Default constructor
     *
     * @param Db                                      $zdb  Database instance
     * @param null|int|ArrayObject<string,int|string> $args Either a ResultSet row or its id for to load
     *                                                      a specific subscription, or null to just
     *                                                      instanciate object
     */
    public function __construct(Db $zdb, int|ArrayObject|null $args = null)
    {
        $this->zdb = $zdb;
        $this->setFields();

        if (is_int($args)) {
            $this->load($args);
        } elseif (is_object($args)) {
            $this->loadFromRS($args);
        }
    }

    /**
     * Loads a subscription from its id
     *
     * @param int $id the identifiant for the subscription to load
     *
     * @return boolean
     */
    public function load(int $id): bool
    {
        try {
            $select = $this->zdb->select($this->getTableName());
            $select->where(array(self::PK => $id));

            $results = $this->zdb->execute($select);

            if ($results->count() > 0) {
                $this->loadFromRS($results->current());
                return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            Analog::log(
                'Cannot load subscription form id `' . $id . '` | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Populate object from a resultset row
     *
     * @param ArrayObject<string, int|string> $r the resultset row
     *
     * @return void
     */
    private function loadFromRS(ArrayObject $r): void
    {
        $this->id = (int)$r->id_subscription;
        $this->setActivity((int)$r->{Activity::PK});
        $this->setMember((int)$r->{Adherent::PK});
        $this->paid = (bool)$r->is_paid;
        $this->payment_amount = (float)$r->payment_amount;
        $this->payment_method = (int)$r->payment_method;
        $this->creation_date = $r->creation_date;
        $this->subscription_date = $r->subscription_date;
        $this->end_date = $r->end_date;
        $this->comment = $r->comment ?? '';
    }

    /**
     * Remove specified subscription
     *
     * @return boolean
     */
    public function remove(): bool
    {
        $transaction = false;

        try {
            if (!$this->zdb->connection->inTransaction()) {
                $this->zdb->connection->beginTransaction();
                $transaction = true;
            }

            $delete = $this->zdb->delete($this->getTableName());
            $delete->where([self::PK => $this->id]);
            $this->zdb->execute($delete);

            //commit all changes
            if ($transaction) {
                $this->zdb->connection->commit();
            }

            return true;
        } catch (\Exception $e) {
            if ($transaction) {
                $this->zdb->connection->rollBack();
            }
            Analog::log(
                'Unable to delete subscription ' .
                ' (' . $this->id . ') |' . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Check posted values validity
     *
     * @param array<string,mixed> $values All values to check, basically the $_POST array
     *                                    after sending the form
     *
     * @return boolean
     */
    public function check(array $values): bool
    {
        $this->errors = array();

        if (!isset($values['activity']) || empty($values['activity']) || $values['activity'] == -1) {
            $this->errors[] = _T('Activity is mandatory', 'activities');
        } else {
            $this->setActivity((int)$values['activity']);
        }

        //financial information
        if (isset($values['paid'])) {
            $this->paid = true;
        } else {
            $this->paid = false;
        }

        if (isset($values['payment_amount']) && !empty($values['payment_amount'])) {
            $this->payment_amount = (float)$values['payment_amount'];
        } else {
            if ($this->getActivity()) {
                $this->payment_amount = $this->getActivity()->getPrice();
            }
        }

        if (isset($values['payment_method'])) {
            $this->payment_method = (int)$values['payment_method'];
        }

        if (!isset($values['member']) || empty($values['member'])) {
            $this->errors[] = _T('Member is mandatory', 'activities');
        } else {
            $this->setMember((int)$values['member']);
        }

        if (isset($values['comment'])) {
            $this->comment = $values['comment'];
        }

        if (!isset($values['subscription_date']) || empty($values['subscription_date'])) {
            $this->errors[] = _T('Subscription date is mandatory', 'activities');
        } else {
            $this->setDate('subscription_date', $values['subscription_date']);
        }

        if (!isset($values['end_date']) || empty($values['end_date'])) {
            $this->errors[] = _T('End date is mandatory', 'activities');
        } else {
            $this->setDate('end_date', $values['end_date']);
        }

        if (count($this->errors) > 0) {
            Analog::log(
                'Some errors has been threw attempting to edit/store a subscription' . "\n" .
                print_r($this->errors, true),
                Analog::ERROR
            );
            return false;
        }

        return true;
    }

    /**
     * Store the subscription
     *
     * @return boolean
     */
    public function store(): bool
    {
        global $hist;

        try {
            $this->zdb->connection->beginTransaction();
            $values = array(
                Activity::PK        => $this->id_activity,
                Adherent::PK        => $this->id_member,
                'is_paid'           => ($this->paid ?:
                                            ($this->zdb->isPostgres() ? 'false' : 0)),
                'payment_method'    => $this->payment_method,
                'payment_amount'    => $this->payment_amount,
                'subscription_date' => $this->subscription_date,
                'end_date'          => $this->end_date,
                'comment'           => $this->comment
            );

            if (!isset($this->id) || $this->id == '') {
                //we're inserting a new subscription
                $this->creation_date = date("Y-m-d");
                $values['creation_date'] = $this->creation_date;

                $insert = $this->zdb->insert($this->getTableName());
                $insert->values($values);
                $add = $this->zdb->execute($insert);
                if ($add->count() > 0) {
                    if ($this->zdb->isPostgres()) {
                        /** @phpstan-ignore-next-line */
                        $this->id = (int)$this->zdb->driver->getLastGeneratedValue(
                            PREFIX_DB . ACTIVITIES_PREFIX . self::TABLE . '_id_seq'
                        );
                    } else {
                        $this->id = (int)$this->zdb->driver->getLastGeneratedValue();
                    }

                    // logging
                    $hist->add(
                        _T("Subscription added", "activities"),
                        $this->getActivity()->getName()
                    );

                    //link member to activity group, if any
                    $group = $this->activity->getGroup();
                    if ($group !== null) {
                        $group->addMember($this->member);
                    }
                } else {
                    $hist->add(_T("Fail to add new subscription.", "activities"));
                    throw new \Exception(
                        'An error occurred inserting new subscription!'
                    );
                }
            } else {
                //we're editing an existing subscription
                $values[self::PK] = $this->id;
                $update = $this->zdb->update($this->getTableName());
                $update
                    ->set($values)
                    ->where([self::PK => $this->id]);

                $edit = $this->zdb->execute($update);

                //edit == 0 does not mean there were an error, but that there
                //were nothing to change
                if ($edit->count() > 0) {
                    $hist->add(
                        _T("Subscription updated", "activities")
                    );
                }
            }

            $this->zdb->connection->commit();
            return true;
        } catch (\Exception $e) {
            $this->zdb->connection->rollBack();
            Analog::log(
                'Something went wrong :\'( | ' . $e->getMessage() . "\n" .
                $e->getTraceAsString(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Get activity id
     *
     * @return ?integer
     */
    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    /**
     * Get activity id
     *
     * @return ?integer
     */
    public function getActivityId(): ?int
    {
        return $this->id_activity ?? null;
    }

    /**
     * Get activity
     *
     * @return ?Activity
     */
    public function getActivity(): ?Activity
    {
        if (isset($this->id_activity)) {
            $this->activity = new Activity($this->zdb, $this->id_activity);
        }
        return $this->activity;
    }

    /**
     * Get member id
     *
     * @return ?integer
     */
    public function getMemberId(): ?int
    {
        return $this->id_member ?? null;
    }

    /**
     * Get member
     *
     * @return ?Adherent
     */
    public function getMember(): ?Adherent
    {
        if (isset($this->id_member)) {
            $this->member = new Adherent($this->zdb, $this->id_member);
        }
        return $this->member;
    }

    /**
     * Is subscription paid?
     *
     * @return boolean
     */
    public function isPaid(): bool
    {
        return $this->paid;
    }

    /**
     * Get amount
     *
     * @return ?float
     */
    public function getAmount(): ?float
    {
        return $this->payment_amount;
    }

    /**
     * Get payment method
     *
     * @return integer
     */
    public function getPaymentMethod(): int
    {
        return $this->payment_method;
    }

    /**
     * Get payment method name
     *
     * @return string
     */
    public function getPaymentMethodName(): string
    {
        $pt = new PaymentType($this->zdb, $this->payment_method);
        return $pt->getname();
    }

    /**
     * Get creation date
     *
     * @param boolean $formatted Return date formatted, raw if false
     *
     * @return string
     */
    public function getCreationDate(bool $formatted = true): string
    {
        return $this->getDate('creation_date', $formatted) ?? '';
    }

    /**
     * Get subscription date
     *
     * @param boolean $formatted Return date formatted, raw if false
     *
     * @return string
     */
    public function getSubscriptionDate(bool $formatted = true): string
    {
        return $this->getDate('subscription_date', $formatted) ?? '';
    }

    /**
     * Get end date
     *
     * @param boolean $formatted Return date formatted, raw if false
     *
     * @return string
     */
    public function getEndDate(bool $formatted = true): string
    {
        return $this->getDate('end_date', $formatted) ?? '';
    }

    /**
     * Set activity
     *
     * @param integer $activity Activity id
     *
     * @return self
     */
    public function setActivity(int $activity): self
    {
        $this->id_activity = $activity;
        $this->activity = new Activity($this->zdb, $this->id_activity);
        return $this;
    }

    /**
     * Set member
     *
     * @param integer $member Member id
     *
     * @return self
     */
    public function setMember(int $member): self
    {
        $this->id_member = $member;
        $this->member = new Adherent($this->zdb, $this->id_member, false);
        return $this;
    }

    /**
     * Get table's name
     *
     * @return string
     */
    protected function getTableName(): string
    {
        return ACTIVITIES_PREFIX . self::TABLE;
    }

    /**
     * Get comment
     *
     * @return string
     */
    public function getComment(): string
    {
        return $this->comment;
    }

    /**
     * Get row class related to current subscription status
     *
     * @param boolean $public we want the class for public pages
     *
     * @return string the class to apply
     */
    public function getRowClass(bool $public = false): string
    {
        $strclass = 'subscription-' .
            ($this->isPaid() ? 'paid' : 'notpaid');
        return $strclass;
    }

    /**
     * Set fields, must populate $this->fields
     *
     * @return self
     */
    protected function setFields(): self
    {
        $this->fields = array(
            self::PK => array(
                'label'    => 'Subscription id', //not a field in the form
                'propname' => 'id'
            ),
            Activity::PK => array(
                'label'    => _T('Activity', 'activities'),
                'propname' => 'id_activity'
            ),
            Adherent::PK => array(
                'label'    => _T('Member', 'activities'),
                'propname' => 'id_member'
            ),
            'is_paid' => array(
                'label'    => _T('Is paid', 'activities'),
                'propname' => 'is_paid'
            ),
            'payment_amount' => array(
                'label'    => _T('Amount', 'activities'),
                'propname' => 'payment_amount'
            ),
            'payment_method' => array(
                'label'    => _T('Payment method', 'activities'),
                'propname' => 'payment_method'
            ),
            'creation_date' => array(
                'label'    => _T('Creation date', 'activities'),
                'propname' => 'creation_date'
            ),
            'subscription_date' => array(
                'label'    => _T('Subscription date', 'activities'),
                'propname' => 'subscription_date'
            ),
            'end_date'      => array(
                'label'    => _T("End date"),
                'propname' => 'end_date'
            ),
            'comment' => array(
                'label'    => _T('Comment', 'activities'),
                'propname' => 'comment'
            )
        );

        return $this;
    }

    /**
     * Get errors
     *
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
