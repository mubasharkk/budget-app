import React, { useState, useEffect } from 'react';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Cell } from 'recharts';
import axios from 'axios';

export default function MostBoughtItemsChart() {
    const [chartData, setChartData] = useState([]);
    const [categories, setCategories] = useState([]);
    const [categoryOptions, setCategoryOptions] = useState([]);
    const [loading, setLoading] = useState(true);
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState('');
    const [selectedCategory, setSelectedCategory] = useState('');

    const fetchCategoryOptions = async () => {
        try {
            const response = await axios.get('/dashboard/categories');
            setCategoryOptions(response.data.categories);
        } catch (error) {
            console.error('Error fetching category options:', error);
        }
    };

    const fetchChartData = async () => {
        setLoading(true);
        try {
            const params = new URLSearchParams();
            if (startDate) {
                params.append('start_date', startDate);
            }
            if (endDate) {
                params.append('end_date', endDate);
            }
            if (selectedCategory) {
                params.append('category_id', selectedCategory);
            }

            const response = await axios.get(`/dashboard/chart/data?${params.toString()}`);
            setChartData(response.data.data);
            setCategories(response.data.categories);
        } catch (error) {
            console.error('Error fetching chart data:', error);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchCategoryOptions();
        fetchChartData();
    }, []);

    const handleDateFilter = () => {
        fetchChartData();
    };

    const handleCategoryFilter = () => {
        fetchChartData();
    };

    const handleClearFilter = () => {
        setStartDate('');
        setEndDate('');
        setSelectedCategory('');
        fetchChartData();
    };

    // Color palette for different categories
    const colors = ['#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6', '#EC4899', '#06B6D4', '#84CC16'];
    
    const getCategoryColor = (category, categories) => {
        const index = categories.indexOf(category);
        return colors[index % colors.length];
    };

    return (
        <div className="p-6">
            <div className="mb-6">
                <h3 className="text-lg font-semibold text-gray-900 mb-2">Most Bought Items</h3>
                <p className="text-sm text-gray-600 mb-4">Top 10 items by quantity purchased</p>
                
                {/* Date Filter Controls */}
                <div className="flex flex-wrap gap-4 mb-6">
                    <div className="flex-1 min-w-48">
                        <label className="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                        <input
                            type="date"
                            value={startDate}
                            onChange={(e) => setStartDate(e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                    <div className="flex-1 min-w-48">
                        <label className="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                        <input
                            type="date"
                            value={endDate}
                            onChange={(e) => setEndDate(e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                    <div className="flex-1 min-w-48">
                        <label className="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <select
                            value={selectedCategory}
                            onChange={(e) => setSelectedCategory(e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">All Categories</option>
                            {categoryOptions.map((category) => (
                                <option key={category.id} value={category.id}>
                                    {category.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="flex gap-2 items-end">
                        <button
                            onClick={handleDateFilter}
                            className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            Apply Filter
                        </button>
                        <button
                            onClick={handleCategoryFilter}
                            className="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500"
                        >
                            Filter by Category
                        </button>
                        <button
                            onClick={handleClearFilter}
                            className="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500"
                        >
                            Clear Filter
                        </button>
                    </div>
                </div>
            </div>

            {/* Chart */}
            {loading ? (
                <div className="flex justify-center items-center h-96">
                    <div className="text-gray-600">Loading chart data...</div>
                </div>
            ) : chartData.length > 0 ? (
                <div className="h-[500px]">
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart 
                            data={chartData} 
                            margin={{ top: 20, right: 30, left: 120, bottom: 60 }}
                            layout="horizontal"
                        >
                            <CartesianGrid strokeDasharray="3 3" />
                            <XAxis type="number" />
                            <YAxis 
                                type="category" 
                                dataKey="item"
                                width={120}
                                fontSize={12}
                            />
                            <Tooltip 
                                formatter={(value, name, props) => [
                                    `${value} units`,
                                    `Category: ${props.payload.category}`
                                ]}
                            />
                            <Bar dataKey="quantity">
                                {chartData.map((entry, index) => (
                                    <Cell 
                                        key={`cell-${index}`} 
                                        fill={getCategoryColor(entry.category, categories)} 
                                    />
                                ))}
                            </Bar>
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            ) : (
                <div className="flex justify-center items-center h-96">
                    <div className="text-gray-500">No data available for the selected date range</div>
                </div>
            )}
        </div>
    );
}