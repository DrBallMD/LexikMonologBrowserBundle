<?php

namespace Lexik\Bundle\MonologBrowserBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Connection;
use Symfony\Component\Form\Extension\Core\Type\SearchType;

/**
 * @author Jeremy Barthe <j.barthe@lexik.fr>
 */
class LogSearchType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('term', 'search', array(
                'required' => false,
            ))
            ->add('level', 'choice', array(
                'choices'     => $options['log_levels'],
                'required'    => false,
            ))
            ->add('date_from', 'datetime', array(
                'widget' => 'single_text',
                'format' => 'd.M.y H:m:s',
                'date_format' => 'd.M.y H:m:s',
                'required'    => false,
            ))
            ->add('date_to', 'datetime', array(
                'widget' => 'single_text',
                'format' => 'd.M.y H:m:s',
                'date_format' => 'd.M.y H:m:s',
                'required'    => false,
            ))
        ;       
        
        $qb = $options['query_builder'];
        $convertDateToDatabaseValue = function(\DateTime $date) use ($qb) {
            return Type::getType('datetime')->convertToDatabaseValue($date, $qb->getConnection()->getDatabasePlatform());
        };

        $builder->addEventListener(FormEvents::POST_SUBMIT, function(FormEvent $event) use ($qb, $convertDateToDatabaseValue) {
            $data = $event->getData();

            if (null !== $data['term']) {
                $qb->andWhere('l.message LIKE :message')
                   ->setParameter('message', '%'.str_replace(' ', '%', $data['term']).'%')
                   ->orWhere('l.channel LIKE :channel')
                   ->setParameter('channel', $data['term'].'%');
            }

            if (null !== $data['level']) {
                $qb->andWhere('l.level = :level')
                   ->setParameter('level', $data['level']);
            }

            if (isset($data['date_from']) && $data['date_from'] instanceof \DateTime) {
                $qb->andWhere('l.datetime >= :date_from')
                   ->setParameter('date_from', $convertDateToDatabaseValue($data['date_from']));
            }

            if (isset($data['date_to']) && $data['date_to'] instanceof \DateTime) {
                $qb->andWhere('l.datetime <= :date_to')
                   ->setParameter('date_to', $convertDateToDatabaseValue($data['date_to']));
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setRequired(array(
                'query_builder',
            ))
            ->setDefaults(array(
                'log_levels'      => array(),
                'csrf_protection' => false,
            ))
            ->addAllowedTypes('log_levels','array')
            ->addAllowedTypes('query_builder','\Doctrine\DBAL\Query\QueryBuilder')
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'search';
    }
}
