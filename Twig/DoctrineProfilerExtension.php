<?php

namespace Pixers\DoctrineProfilerBundle\Twig;

/**
 * DoctrineProfilerExtension.
 *
 * @author Bartłomiej Ojrzeński <bartlomiej.ojrzenski@pixers.pl>
 */
class DoctrineProfilerExtension extends \Twig_Extension
{

    /**
     * @return array
     */
    public function getFilters()
    {
        return array(
            new \Twig_SimpleFilter('doctrine_profiler_minify_query', array($this,'minifyQuery'))
        );
    }
    
    /**
     * @param string $sql
     * @return string
     */
    public function minifyQuery($sql)
    {
        return preg_replace('/SELECT.+FROM/', 'SELECT […] FROM', $sql);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'doctrine_profiler_extension';
    }
}
