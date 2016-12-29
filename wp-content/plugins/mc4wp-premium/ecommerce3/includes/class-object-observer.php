<?php

class MC4WP_Ecommerce_Object_Observer {

    /**
     * @var MC4WP_Queue
     */
    protected $queue;

    /**
     * @var MC4WP_Ecommerce
     */
    protected $ecommerce;

    /**
     * MC4WP_Ecommerce_Scheduler constructor.
     *
     * @param MC4WP_Ecommerce $ecommerce
     * @param MC4WP_Queue $queue
     */
    public function __construct( MC4WP_Ecommerce $ecommerce, MC4WP_Queue $queue ) {
        $this->ecommerce = $ecommerce;
        $this->queue = $queue;
    }

    /**
     * Hook
     */
    public function hook() {
        // update products
        add_action( 'save_post_product', array( $this, 'on_product_save' ) );

        // update or delete orders
        add_action( 'save_post_shop_order', array( $this, 'on_order_save' ) );

        // delete products & orders when they're deleted in WP
        add_action( 'delete_post', array( $this, 'on_post_delete') );

        // updating users
        add_action( 'profile_update', array( $this, 'on_user_update' ) );
    }

    /**
     * Remove pending jobs from the queue
     *
     * @param string $method
     * @param int $object_id
     */
    private function remove_pending_jobs( $method, $object_id ) {
        $jobs = $this->queue->all();
        foreach( $jobs as $job ) {
            if( $job->data['method'] === $method && $job->data['args'][0] == $object_id ) {
                $this->queue->delete( $job );
            }
        }
    }

    /**
     * Add a job to the queue.
     *
     * @param string $method
     * @param int $object_id
     */
    private function add_pending_job( $method, $object_id ) {
        $this->queue->put(
            array(
                'method' => $method,
                'args' => array( $object_id )
            )
        );
    }

    // hook: save_post_product
    public function on_product_save( $post_id ) {
        // skip auto saves
        if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        $this->add_pending_job( 'add_product', $post_id );
    }

    // hook: save_post_shop_order
    public function on_order_save( $post_id ) {
        // skip auto saves
        if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        $statuses = mc4wp_ecommerce_get_order_statuses();
        $post = get_post( $post_id );
        $method = in_array( $post->post_status, $statuses ) ? 'add_order' : 'delete_order';
        $reversed_method = $method === 'add_order' ? 'delete_order' : 'add_order';

        // remove all previous pending jobs which would be reversed by this new job anyway.
        $this->remove_pending_jobs( $reversed_method, $post_id );

        // add new job
        $this->add_pending_job( $method, $post_id );
    }

    // hook: delete_post
    public function on_post_delete( $post_id ) {
        $post = get_post( $post_id );

        // products
        if( $post->post_type === 'product' ) {
            $this->remove_pending_jobs( 'add_product', $post_id );
            $this->add_pending_job( 'delete_product', $post_id );
        }

        // orders
        if( $post->post_type === 'shop_order' ) {
            $this->remove_pending_jobs( 'add_order', $post_id );
            $this->add_pending_job( 'delete_order', $post_id );
        }
    }

    // hook: profile_update
    public function on_user_update( $user_id ) {
        $user = get_userdata( $user_id );

        // was updated user a customer with an email address?
        if( in_array( 'customer', $user->roles ) && ! empty( $user->billing_email ) ) {
            $this->add_pending_job( 'update_customer', $user_id );
        }
    }

}