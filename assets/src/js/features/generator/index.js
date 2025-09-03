/**
 * Plugin Generator Feature
 * Modern interface for generating WordPress plugins with AI
 */

import React, { useState, useCallback, useRef, useEffect } from 'react';
import { render } from 'react-dom';
import {
    Card,
    CardHeader,
    CardBody,
    CardFooter,
    Button,
    TextareaControl,
    SelectControl,
    ToggleControl,
    Notice,
    Spinner,
    Modal,
    __experimentalHStack as HStack,
    __experimentalVStack as VStack,
    __experimentalText as Text,
    __experimentalHeading as Heading
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { motion, AnimatePresence } from 'framer-motion';
import apiClient from '@api';
import visualFeedback from '@components/visual-feedback';
import CodeEditor from '@components/code-editor';
import { useLocalStorage } from '@hooks/useLocalStorage';
import { useDebouncedCallback } from '@hooks/useDebouncedCallback';

/**
 * Generator Component
 */
function Generator() {
    // State management
    const [step, setStep] = useState('description'); // description, plan, code, complete
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [description, setDescription] = useLocalStorage('generator_description', '');
    const [plan, setPlan] = useState(null);
    const [code, setCode] = useState('');
    const [pluginName, setPluginName] = useState('');
    const [selectedModel, setSelectedModel] = useLocalStorage('generator_model', wpAutoPlugin.settings.defaultModel);
    const [showAdvanced, setShowAdvanced] = useState(false);
    const [temperature, setTemperature] = useState(0.7);
    const [activateAfterCreation, setActivateAfterCreation] = useState(true);

    // Refs
    const abortControllerRef = useRef(null);
    const codeEditorRef = useRef(null);

    // Auto-save description
    const saveDescription = useDebouncedCallback((value) => {
        setDescription(value);
    }, 500);

    // Handle step navigation
    const goToStep = useCallback((newStep) => {
        setStep(newStep);
        setError(null);
    }, []);

    // Generate plan
    const generatePlan = useCallback(async () => {
        if (!description.trim()) {
            setError(__('Please enter a plugin description', 'wp-autoplugin'));
            return;
        }

        setLoading(true);
        setError(null);

        try {
            const response = await apiClient.generatePlan(description, {
                model: selectedModel,
                temperature: temperature
            });

            setPlan(response.plan);
            setPluginName(response.plugin_name || 'My Custom Plugin');
            goToStep('plan');
        } catch (err) {
            setError(err.message);
            visualFeedback.showToast(err.message, 'error');
        } finally {
            setLoading(false);
        }
    }, [description, selectedModel, temperature, goToStep]);

    // Generate code
    const generateCode = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            const response = await apiClient.generateCode(plan, {
                model: selectedModel,
                temperature: temperature
            });

            setCode(response.code);
            goToStep('code');
        } catch (err) {
            setError(err.message);
            visualFeedback.showToast(err.message, 'error');
        } finally {
            setLoading(false);
        }
    }, [plan, selectedModel, temperature, goToStep]);

    // Create plugin
    const createPlugin = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            const response = await apiClient.createPlugin(code, pluginName, {
                activate: activateAfterCreation
            });

            visualFeedback.showToast(
                __('Plugin created successfully!', 'wp-autoplugin'),
                'success'
            );

            // Clear saved data
            setDescription('');
            setPlan(null);
            setCode('');
            
            goToStep('complete');

            // Redirect after delay
            setTimeout(() => {
                window.location.href = response.redirect_url || '/wp-admin/plugins.php';
            }, 2000);
        } catch (err) {
            setError(err.message);
            visualFeedback.showToast(err.message, 'error');
        } finally {
            setLoading(false);
        }
    }, [code, pluginName, activateAfterCreation, goToStep, setDescription]);

    // Cancel current operation
    const cancelOperation = useCallback(() => {
        if (abortControllerRef.current) {
            abortControllerRef.current.abort();
            abortControllerRef.current = null;
        }
        setLoading(false);
    }, []);

    // Render step content
    const renderStepContent = () => {
        switch (step) {
            case 'description':
                return (
                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: -20 }}
                    >
                        <VStack spacing={4}>
                            <TextareaControl
                                label={__('Describe Your Plugin', 'wp-autoplugin')}
                                help={__('Be specific about features, functionality, and requirements', 'wp-autoplugin')}
                                value={description}
                                onChange={saveDescription}
                                rows={8}
                                placeholder={__('I need a plugin that...', 'wp-autoplugin')}
                            />

                            <Card size="small" variant="secondary">
                                <CardBody>
                                    <Heading level={5}>{__('Tips for Better Results:', 'wp-autoplugin')}</Heading>
                                    <ul style={{ marginLeft: '20px', marginTop: '10px' }}>
                                        <li>{__('Be specific about features and functionality', 'wp-autoplugin')}</li>
                                        <li>{__('Mention any integrations or dependencies', 'wp-autoplugin')}</li>
                                        <li>{__('Specify user roles or permissions if needed', 'wp-autoplugin')}</li>
                                        <li>{__('Include UI/UX preferences', 'wp-autoplugin')}</li>
                                    </ul>
                                </CardBody>
                            </Card>

                            <Button
                                variant="link"
                                onClick={() => setShowAdvanced(!showAdvanced)}
                            >
                                {showAdvanced ? __('Hide Advanced Options', 'wp-autoplugin') : __('Show Advanced Options', 'wp-autoplugin')}
                            </Button>

                            {showAdvanced && (
                                <Card>
                                    <CardBody>
                                        <VStack spacing={3}>
                                            <SelectControl
                                                label={__('AI Model', 'wp-autoplugin')}
                                                value={selectedModel}
                                                onChange={setSelectedModel}
                                                options={Object.entries(wpAutoPlugin.models).map(([value, label]) => ({
                                                    value,
                                                    label
                                                }))}
                                            />
                                            <div>
                                                <label>{__('Temperature', 'wp-autoplugin')} ({temperature})</label>
                                                <input
                                                    type="range"
                                                    min="0"
                                                    max="1"
                                                    step="0.1"
                                                    value={temperature}
                                                    onChange={(e) => setTemperature(parseFloat(e.target.value))}
                                                    style={{ width: '100%' }}
                                                />
                                                <Text variant="muted" size="small">
                                                    {__('Lower = more focused, Higher = more creative', 'wp-autoplugin')}
                                                </Text>
                                            </div>
                                        </VStack>
                                    </CardBody>
                                </Card>
                            )}
                        </VStack>
                    </motion.div>
                );

            case 'plan':
                return (
                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: -20 }}
                    >
                        <VStack spacing={4}>
                            <Card>
                                <CardHeader>
                                    <Heading level={4}>{__('Plugin Plan', 'wp-autoplugin')}</Heading>
                                </CardHeader>
                                <CardBody>
                                    <PlanDisplay plan={plan} />
                                </CardBody>
                            </Card>

                            <Card size="small">
                                <CardBody>
                                    <VStack spacing={2}>
                                        <Text weight="600">{__('Plugin Name:', 'wp-autoplugin')}</Text>
                                        <input
                                            type="text"
                                            value={pluginName}
                                            onChange={(e) => setPluginName(e.target.value)}
                                            className="components-text-control__input"
                                            style={{ width: '100%' }}
                                        />
                                    </VStack>
                                </CardBody>
                            </Card>

                            <HStack justify="space-between">
                                <Button
                                    variant="secondary"
                                    onClick={() => goToStep('description')}
                                >
                                    {__('Back', 'wp-autoplugin')}
                                </Button>
                                <Button
                                    variant="primary"
                                    onClick={generateCode}
                                    disabled={loading}
                                >
                                    {loading ? <Spinner /> : __('Generate Code', 'wp-autoplugin')}
                                </Button>
                            </HStack>
                        </VStack>
                    </motion.div>
                );

            case 'code':
                return (
                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: -20 }}
                    >
                        <VStack spacing={4}>
                            <Card>
                                <CardHeader>
                                    <HStack justify="space-between">
                                        <Heading level={4}>{__('Generated Code', 'wp-autoplugin')}</Heading>
                                        <Button
                                            variant="secondary"
                                            size="small"
                                            onClick={() => {
                                                navigator.clipboard.writeText(code);
                                                visualFeedback.showToast(__('Code copied to clipboard', 'wp-autoplugin'), 'success');
                                            }}
                                        >
                                            {__('Copy Code', 'wp-autoplugin')}
                                        </Button>
                                    </HStack>
                                </CardHeader>
                                <CardBody>
                                    <CodeEditor
                                        ref={codeEditorRef}
                                        value={code}
                                        onChange={setCode}
                                        language="php"
                                        height="400px"
                                    />
                                </CardBody>
                            </Card>

                            <Card size="small">
                                <CardBody>
                                    <ToggleControl
                                        label={__('Activate plugin after creation', 'wp-autoplugin')}
                                        checked={activateAfterCreation}
                                        onChange={setActivateAfterCreation}
                                    />
                                </CardBody>
                            </Card>

                            <HStack justify="space-between">
                                <Button
                                    variant="secondary"
                                    onClick={() => goToStep('plan')}
                                >
                                    {__('Back', 'wp-autoplugin')}
                                </Button>
                                <Button
                                    variant="primary"
                                    onClick={createPlugin}
                                    disabled={loading}
                                >
                                    {loading ? <Spinner /> : __('Create Plugin', 'wp-autoplugin')}
                                </Button>
                            </HStack>
                        </VStack>
                    </motion.div>
                );

            case 'complete':
                return (
                    <motion.div
                        initial={{ opacity: 0, scale: 0.9 }}
                        animate={{ opacity: 1, scale: 1 }}
                        className="success-screen"
                        style={{ textAlign: 'center', padding: '40px' }}
                    >
                        <div style={{ fontSize: '64px', marginBottom: '20px' }}>🎉</div>
                        <Heading level={2}>{__('Plugin Created Successfully!', 'wp-autoplugin')}</Heading>
                        <Text>{__('Redirecting to plugins page...', 'wp-autoplugin')}</Text>
                    </motion.div>
                );
        }
    };

    return (
        <div className="wp-autoplugin-generator">
            <Card>
                <CardHeader>
                    <Heading level={3}>{__('AI Plugin Generator', 'wp-autoplugin')}</Heading>
                </CardHeader>
                <CardBody>
                    {/* Progress indicator */}
                    <StepIndicator currentStep={step} />

                    {/* Error display */}
                    {error && (
                        <Notice status="error" isDismissible onRemove={() => setError(null)}>
                            {error}
                        </Notice>
                    )}

                    {/* Step content */}
                    <AnimatePresence mode="wait">
                        {renderStepContent()}
                    </AnimatePresence>
                </CardBody>
                <CardFooter>
                    {step === 'description' && (
                        <Button
                            variant="primary"
                            onClick={generatePlan}
                            disabled={loading || !description.trim()}
                        >
                            {loading ? <Spinner /> : __('Generate Plan', 'wp-autoplugin')}
                        </Button>
                    )}
                </CardFooter>
            </Card>

            {/* Loading overlay */}
            {loading && (
                <div className="loading-overlay" style={{
                    position: 'fixed',
                    top: 0,
                    left: 0,
                    right: 0,
                    bottom: 0,
                    background: 'rgba(0, 0, 0, 0.5)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    zIndex: 99999
                }}>
                    <Card>
                        <CardBody>
                            <VStack spacing={3} alignment="center">
                                <Spinner />
                                <Text>{__('Processing...', 'wp-autoplugin')}</Text>
                                <Button variant="link" onClick={cancelOperation}>
                                    {__('Cancel', 'wp-autoplugin')}
                                </Button>
                            </VStack>
                        </CardBody>
                    </Card>
                </div>
            )}
        </div>
    );
}

/**
 * Step Indicator Component
 */
function StepIndicator({ currentStep }) {
    const steps = [
        { id: 'description', label: __('Description', 'wp-autoplugin') },
        { id: 'plan', label: __('Plan', 'wp-autoplugin') },
        { id: 'code', label: __('Code', 'wp-autoplugin') },
        { id: 'complete', label: __('Complete', 'wp-autoplugin') }
    ];

    const currentIndex = steps.findIndex(s => s.id === currentStep);

    return (
        <div className="step-indicator" style={{ marginBottom: '30px' }}>
            <HStack spacing={2} alignment="center">
                {steps.map((step, index) => (
                    <React.Fragment key={step.id}>
                        <div
                            className={`step-circle ${index <= currentIndex ? 'active' : ''}`}
                            style={{
                                width: '32px',
                                height: '32px',
                                borderRadius: '50%',
                                background: index <= currentIndex ? '#3b82f6' : '#e5e7eb',
                                color: index <= currentIndex ? 'white' : '#64748b',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                fontWeight: 600,
                                fontSize: '14px',
                                transition: 'all 0.3s ease'
                            }}
                        >
                            {index < currentIndex ? '✓' : index + 1}
                        </div>
                        {index < steps.length - 1 && (
                            <div
                                className="step-line"
                                style={{
                                    flex: 1,
                                    height: '2px',
                                    background: index < currentIndex ? '#3b82f6' : '#e5e7eb',
                                    transition: 'all 0.3s ease'
                                }}
                            />
                        )}
                    </React.Fragment>
                ))}
            </HStack>
            <HStack spacing={2} alignment="center" style={{ marginTop: '8px' }}>
                {steps.map((step, index) => (
                    <Text
                        key={step.id}
                        size="small"
                        weight={index === currentIndex ? 600 : 400}
                        style={{
                            flex: index === 0 || index === steps.length - 1 ? '0' : '1',
                            textAlign: 'center',
                            color: index <= currentIndex ? '#1e293b' : '#94a3b8'
                        }}
                    >
                        {step.label}
                    </Text>
                ))}
            </HStack>
        </div>
    );
}

/**
 * Plan Display Component
 */
function PlanDisplay({ plan }) {
    if (!plan) return null;

    const sections = Object.entries(plan).filter(([key]) => key !== 'testing_plan');

    return (
        <VStack spacing={3}>
            {sections.map(([key, value]) => (
                <Card key={key} size="small">
                    <CardHeader>
                        <Text weight="600" transform="capitalize">
                            {key.replace(/_/g, ' ')}
                        </Text>
                    </CardHeader>
                    <CardBody>
                        <pre style={{
                            whiteSpace: 'pre-wrap',
                            fontSize: '13px',
                            lineHeight: '1.6',
                            margin: 0
                        }}>
                            {typeof value === 'object' ? JSON.stringify(value, null, 2) : value}
                        </pre>
                    </CardBody>
                </Card>
            ))}
        </VStack>
    );
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('wp-autoplugin-generator-root');
    if (container) {
        render(<Generator />, container);
    }
});