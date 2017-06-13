<?php
/**
 * This file is part of ONP.
 *
 * Copyright (c) 2015 Opensoft (http://opensoftdev.com)
 *
 * The unauthorized use of this code outside the boundaries of
 * Opensoft is prohibited.
 */

namespace Opensoft\StorageBundle\Form\Type;

use Doctrine\ORM\QueryBuilder;
use Lexik\Bundle\FormFilterBundle\Filter\Form\Type\TextFilterType;
use Opensoft\StorageBundle\Entity\StorageFile;
use Lexik\Bundle\FormFilterBundle\Filter\Query\QueryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * @author Dmitriy Katalkin <dmitriy.katalkin@opensoftdev.ru>
 */
class StoredFilesFilterType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('type', ChoiceType::class, [
            'placeholder' => 'All types',
            'required' => false,
            'choices' => array_flip(StorageFile::$types),
        ]);

        $builder->add('size', TextFilterType::class, [
            'apply_filter' => function (QueryInterface $filterQuery, $field, $values) {
                if ($values['value']) {
                    $this->addCondition($filterQuery, $field, $values['value']);
                }
            }
        ]);

        $builder->setMethod('GET');
    }

    /**
     * @param QueryInterface $filterQuery
     * @param $field
     * @param $rawValue
     *
     * @throws \Exception
     */
    protected function addCondition(QueryInterface $filterQuery, $field, $rawValue)
    {
        list($type, $value, $units) = array_pad(explode(':', $rawValue), 3, null);
        if (empty($value) || empty($units)) {
            return;
        }

        $paramName = sprintf(':p_%s', str_replace('.', '_', $field));
        /** @var QueryBuilder $qb */
        $qb = $filterQuery->getQueryBuilder();
        $e = $qb->expr();

        switch ($type) {
            case 'lt':
                $exp = $e->lt($field, $paramName);
                break;
            case 'gt':
                $exp = $e->gt($field, $paramName);
                break;
            default:
                throw new \Exception('Unknown condition type');
        }

        $bytes = $this->convertToBytes($value, $units);
        $qb->andWhere($exp)->setParameter($paramName, $bytes);
    }


    /**
     * @param $value
     * @param $units
     *
     * @return int|null
     */
    protected function convertToBytes($value, $units)
    {
        $exps = [
            'KB' => 10,
            'MB' => 20
        ];

        if (!array_key_exists($units, $exps)) {
            return null;
        }

        $exp = $exps[$units];
        $bytes = (int)(pow(2, $exp) * (float)$value);

        return $bytes;
    }

    /**
     * @return string
     */
    public function getBlockPrefix()
    {
        return 'stored_files_filter_type';
    }
}
