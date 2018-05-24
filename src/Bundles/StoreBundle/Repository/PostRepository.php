<?php

namespace Bundles\StoreBundle\Repository;
use Bundles\UserBundle\Entity\User;
use Bundles\StoreBundle\Entity\Community;
use Doctrine\ORM\QueryBuilder;

/**
 * PostRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class PostRepository extends \Doctrine\ORM\EntityRepository
{
    const POST_TYPE_UPDATE = 'update';
    const POST_TYPE_OPPORTUNITY = 'opportunity';
    const POST_TYPE_EVENT = 'event';

    public function getUserWallPost(User $user, $type)
    {
        $qb = $this->createQueryBuilder('post')
            ->leftJoin('post.sharedUsers', 'shu')
            ->where('post.author = :user')
            ->orWhere('shu.user = :user')
            ->setParameter('user', $user);
        if ($type) {
            $qb->andWhere('post.type = :type')
                ->setParameter('type', $type);
        }
        $posts = $qb->getQuery()->getResult();
        foreach ($posts as $post) {
            if ($post->getAuthor() == $user) {
                $post->showDate = $post->getCreatedAt();
            } else {
                $date = $this->createQueryBuilder('post')
                        ->leftJoin('post.sharedUsers', 'shu')
                        ->select('shu.createdAt as showDate')
                        ->where('shu.user = :user')
                        ->setParameter('user', $user)
                        ->andWhere('shu.post = :post')
                        ->setParameter('post', $post)
                        ->getQuery()
                        ->getSingleResult();
                $post->showDate = $date['showDate'];
            }          
        }
        $returnPosts = $this->bubbleSort($posts);

        return $returnPosts;
    }

    /**
     * @param $comm
     * @param string $type
     * @param null $user
     * @param null string $postCategory
     * @param null string $locale
     * @return array
     */
    public function getNewsPost($comm, $type, $user = null, $postCategory = null, $locale = null)
    {
        $qb = $this->createQueryBuilder('post')
            ->join('post.communities', 'comm')
            ->where('comm.id IN (:comm)')
            ->setParameter('comm', $comm);

        if ($type) {
            $qb->andWhere('post.type = :type')
                ->setParameter('type', $type);
        }

        if ($user) {
            $qb->andWhere('post.author != :user')
                ->setParameter('user', $user);
        }

        if (!empty($postCategory)) {
            $this->applyPostCategoryFiltration($qb, $type, $postCategory, $locale);
        }

        $posts = $qb->orderBy('post.createdAt', 'DESC')->getQuery()->getResult();

        foreach ($posts as $post) {
            $post->showDate = $post->getCreatedAt();
        }

        return $posts;
    }

    public function getFriendsPosts($friends, $type = null)
    {
        $qb = $this->createQueryBuilder('post')
                ->where('post.author IN (:friends)')
                ->setParameter('friends', $friends);

        if ($type) {
            $qb->where('post.type = :type')
                ->setParameter('type', $type);
        }

        $posts = $qb->orderBy('post.createdAt', 'DESC')->getQuery()->getResult();

        return $posts;
    }
    
    public function getCommunityPost(Community $comm, $type = null)
    {
        $qb = $this->createQueryBuilder('post')
                ->where(':comm MEMBER OF post.communities')

                ->setParameter('comm', $comm);
        if ($type) {
            $qb->andWhere('post.type = :type')
                ->setParameter('type', $type);
        }

        return $qb->orderBy('post.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
    
    public function getLasWeekCommunityPost(Community $comm, $from, $to, $type)
    {
        return $this->createQueryBuilder('post')
            ->where(':comm MEMBER OF post.communities')
            ->setParameter('comm', $comm)
            ->andWhere('post.type = :type')
            ->setParameter('type', $type)
            ->andWhere('post.createdAt BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('post.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param $qb
     * @param $type
     * @param $postCategory
     * @param $locale
     */
    private function applyPostCategoryFiltration($qb, $type, $postCategory, $locale)
    {
        switch ($type) {
            case static::POST_TYPE_UPDATE :
                $this->getUpdatePostsByPostCategoryQuery($qb, $postCategory, $locale);
                break;

            case static::POST_TYPE_EVENT :
                $this->getEventPostByEventTypeQuery($qb, $postCategory, $locale);
                break;

            case static::POST_TYPE_OPPORTUNITY :
                $this->getOpportunityPostByProjectTypeQuery($qb, $postCategory, $locale);
                break;
        }
    }

    /**
     * @param QueryBuilder $qb
     * @param string $postCategory
     * @param string $locale
     * @return QueryBuilder
     */
    private function getUpdatePostsByPostCategoryQuery(QueryBuilder $qb, $postCategory, $locale)
    {
        return $qb
            ->join('post.updatePost','update_post', 'WITH', 'post.updatePost = update_post.id')
            ->join('BundlesOptionBundle:PostCategoryTranslation', 'post_category_translation',
                'WITH', 'update_post.postCategory = post_category_translation.translatable')
            ->where('post_category_translation.locale = :locale')
            ->andWhere('post_category_translation.name = :postCategory')
            ->setParameters(['locale' => $locale, 'postCategory' => $postCategory]);
    }

    /**
     * @param QueryBuilder $qb
     * @param string $projectType
     * @param string $locale
     * @return QueryBuilder
     */
    private function getOpportunityPostByProjectTypeQuery(QueryBuilder $qb, $projectType, $locale)
    {
        return $qb
            ->join('post.opportunityPost','opportunity_post',
                'WITH', 'post.opportunityPost = opportunity_post.id')
            ->join('BundlesOptionBundle:ProjectTypeTranslation', 'project_type_translation',
                'WITH', 'opportunity_post.projectType = project_type_translation.translatable')
            ->where('project_type_translation.locale = :locale')
            ->andWhere('project_type_translation.name = :projectType')
            ->setParameters(['locale' => $locale, 'projectType' => $projectType]);
    }

    /**
     * @param QueryBuilder $qb
     * @param string $eventType
     * @param string $locale
     * @return QueryBuilder
     */
    private function getEventPostByEventTypeQuery(QueryBuilder $qb, $eventType, $locale)
    {
        return $qb
            ->join('post.eventPost','event_post',
                'WITH', 'post.eventPost = event_post.id')
            ->join('BundlesOptionBundle:EventTypeTranslation', 'event_type_translation',
                'WITH', 'event_post.eventType = event_type_translation.translatable')
            ->where('event_type_translation.locale = :locale')
            ->andWhere('event_type_translation.name = :eventType')
            ->setParameters(['locale' => $locale, 'eventType' => $eventType]);
    }
    
    private function bubbleSort($array) {
        $count = count($array);
        for ($i = $count-1; $i >= 0; $i--) {
            for ($j = 0; $j < $i; $j++ ) {
                if ($array[$j]->showDate < $array[$j+1]->showDate) {
                    $temp = $array[$j];
                    $array[$j] = $array[$j+1];
                    $array[$j+1] = $temp;
                }
            }
        }
        return $array;
    }
}
