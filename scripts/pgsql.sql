--
-- Table structure for table `galette_activities_activities`
--

DROP SEQUENCE IF EXISTS galette_activities_activities_id_seq;
CREATE SEQUENCE galette_activities_activities_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

DROP TABLE IF EXISTS galette_activities_activities CASCADE;
CREATE TABLE galette_activities_activities (
  id_activity integer DEFAULT nextval('galette_activities_activities_id_seq'::text) NOT NULL,
  name character varying(150) NOT NULL,
  type character varying(3) NOT NULL default '',
  price decimal(15,2) NULL DEFAULT NULL,
  id_group integer REFERENCES galette_groups(id_group) ON DELETE RESTRICT ON UPDATE CASCADE default NULL,
  creation_date date NOT NULL,
  comment text,
  PRIMARY KEY (id_activity)
);

--
-- Table structure for table `galette_activities_subscriptions`
--

DROP SEQUENCE IF EXISTS galette_activities_subscriptions_id_seq;
CREATE SEQUENCE galette_activities_subscriptions_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

DROP TABLE IF EXISTS galette_activities_subscriptions CASCADE;
CREATE TABLE galette_activities_subscriptions (
  id_subscription integer DEFAULT nextval('galette_activities_subscriptions_id_seq'::text) NOT NULL,
  id_activity integer REFERENCES galette_activities_activities (id_activity) ON DELETE CASCADE ON UPDATE CASCADE,
  id_adh integer REFERENCES galette_adherents (id_adh) ON DELETE CASCADE ON UPDATE CASCADE,
  is_paid boolean default FALSE,
  payment_amount decimal(15,2) NULL DEFAULT NULL,
  payment_method smallint default '0' NOT NULL,
  creation_date date NOT NULL,
  subscription_date date NOT NULL,
  end_date date NOT NULL,
  comment text,
  PRIMARY KEY (id_subscription)
);
